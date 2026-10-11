<?php

require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/config/config.php';

check_login();

$db = new PDO(
    'sqlite:' . __DIR__ . '/data/neal.db',
    null,
    null,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

$request_id = (int)($_GET['id'] ?? $_POST['request_id'] ?? 0);

if ($request_id <= 0) {
    header('Location: tickets.php');
    exit;
}

$current_admin_id = (int)($_SESSION['admin_id'] ?? 0);
$current_username = $_SESSION['username'] ?? 'کارشناس';

$page_title = 'مدیریت درخواست';


// ============================================================
// Helpers
// ============================================================

function h($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}



/*
 * ------------------------------------------------------------
 * Safe rich-text message handling
 * ------------------------------------------------------------
 */

function sanitize_ticket_message_html(string $html): string
{
    $allowed_tags = '<strong><b><em><i><u><a><ul><ol><li><br><div><p><span>';

    $html = strip_tags($html, $allowed_tags);

    /*
     * Remove dangerous attributes from every HTML tag.
     * Keep only:
     * - href
     * - target
     * - rel
     * - style="text-align: ..."
     */

    $html = preg_replace_callback(
        '/<([a-zA-Z0-9]+)([^>]*)>/',
        function ($match) {

            $tag = strtolower($match[1]);
            $attrs = $match[2];

            if (in_array($tag, ['br'], true)) {
                return '<br>';
            }

            $result = '<' . $tag;

            if (
                preg_match(
                    '/\bhref\s*=\s*([\'"])(.*?)\1/i',
                    $attrs,
                    $m
                )
            ) {
                $href = trim($m[2]);

                if (
                    preg_match(
                        '/^(https?:\/\/|mailto:|tel:)/i',
                        $href
                    )
                ) {
                    $result .= ' href="' . h($href) . '"';
                }
            }

            if (
                preg_match(
                    '/\btarget\s*=\s*([\'"])(.*?)\1/i',
                    $attrs,
                    $m
                )
            ) {
                $target = strtolower(trim($m[2]));

                if (in_array($target, ['_blank', '_self'], true)) {
                    $result .= ' target="' . h($target) . '"';
                }
            }

            if (
                preg_match(
                    '/\brel\s*=\s*([\'"])(.*?)\1/i',
                    $attrs,
                    $m
                )
            ) {
                $rel = trim($m[2]);

                if (preg_match('/^[a-zA-Z0-9 _-]+$/', $rel)) {
                    $result .= ' rel="' . h($rel) . '"';
                }
            }

            if (
                preg_match(
                    '/\bstyle\s*=\s*([\'"])(.*?)\1/i',
                    $attrs,
                    $m
                )
            ) {
                $style = strtolower(trim($m[2]));

                if (
                    preg_match(
                        '/^text-align\s*:\s*(left|right|center|justify)\s*;?$/i',
                        $style
                    )
                ) {
                    $align = preg_replace(
                        '/[^a-z]/',
                        '',
                        $style
                    );

                    $result .= ' style="text-align: ' . h($align) . ';"';
                }
            }

            $result .= '>';

            return $result;
        },
        $html
    );

    /*
     * Extra protection against javascript/data/vbscript URLs
     * that might survive malformed markup.
     */
    $html = preg_replace(
        '/\s+(href|src)\s*=\s*([\'"])\s*(javascript|vbscript|data):.*?\2/iu',
        '',
        $html
    );

    return $html;
}


function ticket_message_html(?string $message): string
{
    $message = (string)$message;

    if ($message === '') {
        return '';
    }

    /*
     * Old messages are plain text.
     * Keep them exactly as text.
     */
    if (
        !preg_match(
            '/<(strong|b|em|i|u|a|ul|ol|li|br|div|p|span)\b/i',
            $message
        )
    ) {
        return nl2br(
            h($message),
            false
        );
    }

    return sanitize_ticket_message_html($message);
}


function jalali_datetime(?string $datetime): string
{
    if (empty($datetime)) {
        return '-';
    }

    $timestamp = strtotime($datetime);

    if ($timestamp === false) {
        return '-';
    }

    $gy = (int)date('Y', $timestamp);
    $gm = (int)date('n', $timestamp);
    $gd = (int)date('j', $timestamp);

    [$jy, $jm, $jd] = gregorian_to_jalali(
        $gy,
        $gm,
        $gd
    );

    return sprintf(
        '%04d/%02d/%02d - %s',
        $jy,
        $jm,
        $jd,
        date('H:i', $timestamp)
    );
}


function gregorian_to_jalali(
    int $gy,
    int $gm,
    int $gd
): array {

    $g_d_m = [
        0,
        31,
        59,
        90,
        120,
        151,
        181,
        212,
        243,
        273,
        304,
        334
    ];

    if ($gy > 1600) {
        $jy = 979;
        $gy -= 1600;
    } else {
        $jy = 0;
        $gy -= 621;
    }

    $gy2 = ($gm > 2)
        ? ($gy + 1)
        : $gy;

    $days =
        (365 * $gy)
        + (int)(($gy2 + 3) / 4)
        - (int)(($gy2 + 99) / 100)
        + (int)(($gy2 + 399) / 400)
        - 80
        + $gd
        + $g_d_m[$gm - 1];

    $jy += 33 * (int)($days / 12053);
    $days %= 12053;

    $jy += 4 * (int)($days / 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }

    return [$jy, $jm, $jd];
}


function status_label(string $status): string
{
    return [
        'new'         => 'جدید',
        'assigned'    => 'ارجاع شده',
        'in_progress' => 'در حال بررسی',
        'waiting_user' => 'منتظر پاسخ کاربر',
        'resolved'    => 'حل شده',
        'closed'      => 'بسته شده',
        'cancelled'   => 'لغو شده'
    ][$status] ?? $status;
}


function priority_label(string $priority): string
{
    return [
        'normal'    => 'عادی',
        'important' => 'مهم',
        'urgent'    => 'فوری',
        'critical'  => 'بحرانی'
    ][$priority] ?? $priority;
}


function status_class(string $status): string
{
    return [
        'new'          => 'status-new',
        'assigned'     => 'status-assigned',
        'in_progress'  => 'status-progress',
        'waiting_user' => 'status-waiting',
        'resolved'     => 'status-resolved',
        'closed'       => 'status-closed',
        'cancelled'    => 'status-cancelled'
    ][$status] ?? 'status-new';
}


function priority_class(string $priority): string
{
    return [
        'normal'    => 'priority-normal',
        'important' => 'priority-important',
        'urgent'    => 'priority-urgent',
        'critical'  => 'priority-critical'
    ][$priority] ?? 'priority-normal';
}


function redirect_ticket(int $id, string $anchor = ''): void
{
    header(
        'Location: ticket-manage.php?id=' .
        $id .
        ($anchor !== '' ? '#' . $anchor : '')
    );
    exit;
}


// ============================================================
// Load ticket
// ============================================================

$stmt = $db->prepare("
    SELECT
        sr.*,
        a.username AS assigned_username
    FROM service_requests sr
    LEFT JOIN admins a
        ON a.id = sr.assigned_to
    WHERE sr.id = ?
    LIMIT 1
");

$stmt->execute([$request_id]);

$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: tickets.php');
    exit;
}


// ============================================================
// POST actions
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {

        // ----------------------------------------------------
        // Change status
        // ----------------------------------------------------

        if ($action === 'change_status') {

            $new_status = trim(
                $_POST['status'] ?? ''
            );

            $allowed_statuses = [
                'new',
                'assigned',
                'in_progress',
                'waiting_user',
                'cancelled'
            ];

            if (
                $ticket['status'] === 'closed' ||
                !in_array(
                    $new_status,
                    $allowed_statuses,
                    true
                )
            ) {
                throw new Exception(
                    'تغییر این وضعیت امکان‌پذیر نیست.'
                );
            }

            $old_status = $ticket['status'];

            if ($old_status === $new_status) {
                redirect_ticket($request_id);
            }

            $assigned_at = $ticket['assigned_at'];
            $started_at  = $ticket['started_at'];

            if (
                $new_status === 'assigned' &&
                empty($assigned_at)
            ) {
                $assigned_at = date('Y-m-d H:i:s');
            }

            if (
                $new_status === 'in_progress' &&
                empty($started_at)
            ) {
                $started_at = date('Y-m-d H:i:s');
            }

            $stmt = $db->prepare("
                UPDATE service_requests
                SET
                    status = ?,
                    assigned_at = ?,
                    started_at = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $stmt->execute([
                $new_status,
                $assigned_at,
                $started_at,
                $request_id
            ]);

            $stmt = $db->prepare("
                INSERT INTO ticket_status_history
                (
                    request_id,
                    old_status,
                    new_status,
                    changed_by,
                    note
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $request_id,
                $old_status,
                $new_status,
                $current_admin_id,
                'تغییر وضعیت توسط کارشناس'
            ]);

            redirect_ticket($request_id);
        }


        // ----------------------------------------------------
        // Change priority
        // ----------------------------------------------------

        if ($action === 'change_priority') {

            $new_priority = trim(
                $_POST['priority'] ?? ''
            );

            $allowed_priorities = [
                'normal',
                'important',
                'urgent',
                'critical'
            ];

            if (
                $ticket['status'] === 'closed' ||
                !in_array(
                    $new_priority,
                    $allowed_priorities,
                    true
                )
            ) {
                throw new Exception(
                    'تغییر اولویت امکان‌پذیر نیست.'
                );
            }

            $old_priority = $ticket['priority'];

            if ($old_priority === $new_priority) {
                redirect_ticket($request_id);
            }

            $stmt = $db->prepare("
                UPDATE service_requests
                SET
                    priority = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $stmt->execute([
                $new_priority,
                $request_id
            ]);

            $stmt = $db->prepare("
                INSERT INTO ticket_status_history
                (
                    request_id,
                    old_status,
                    new_status,
                    changed_by,
                    note
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $request_id,
                $ticket['status'],
                $ticket['status'],
                $current_admin_id,
                'تغییر اولویت از "' .
                priority_label($old_priority) .
                '" به "' .
                priority_label($new_priority) .
                '"'
            ]);

            redirect_ticket($request_id);
        }


        // ----------------------------------------------------
        // Assign / Reassign
        // ----------------------------------------------------

        if ($action === 'assign_ticket') {

            if ($ticket['status'] === 'closed') {
                throw new Exception(
                    'درخواست بسته شده قابل ارجاع مجدد نیست.'
                );
            }

            $to_admin_id = (int)(
                $_POST['assigned_to'] ?? 0
            );

            $assignment_note = trim(
                $_POST['assignment_note'] ?? ''
            );

            if ($to_admin_id <= 0) {
                throw new Exception(
                    'کارشناس مقصد را انتخاب کنید.'
                );
            }

            $stmt = $db->prepare("
                SELECT id, username
                FROM admins
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $to_admin_id
            ]);

            $target_admin = $stmt->fetch();

            if (!$target_admin) {
                throw new Exception(
                    'کارشناس انتخاب‌شده معتبر نیست.'
                );
            }

            $from_admin_id = !empty(
                $ticket['assigned_to']
            )
                ? (int)$ticket['assigned_to']
                : null;

            $new_status = $ticket['status'];

            if (
                $ticket['status'] === 'new' ||
                empty($ticket['assigned_to'])
            ) {
                $new_status = 'assigned';
            }

            $assigned_at = $ticket['assigned_at'];

            if (empty($assigned_at)) {
                $assigned_at = date('Y-m-d H:i:s');
            }

            $db->beginTransaction();

            $stmt = $db->prepare("
                UPDATE service_requests
                SET
                    assigned_to = ?,
                    status = ?,
                    assigned_at = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $stmt->execute([
                $to_admin_id,
                $new_status,
                $assigned_at,
                $request_id
            ]);

            $stmt = $db->prepare("
                INSERT INTO ticket_assignments
                (
                    request_id,
                    from_admin_id,
                    to_admin_id,
                    assigned_by,
                    note
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $request_id,
                $from_admin_id,
                $to_admin_id,
                $current_admin_id,
                $assignment_note !== ''
                    ? $assignment_note
                    : null
            ]);

            if ($new_status !== $ticket['status']) {

                $stmt = $db->prepare("
                    INSERT INTO ticket_status_history
                    (
                        request_id,
                        old_status,
                        new_status,
                        changed_by,
                        note
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $request_id,
                    $ticket['status'],
                    $new_status,
                    $current_admin_id,
                    'ارجاع درخواست به کارشناس ' .
                    $target_admin['username']
                ]);
            }

            $db->commit();

            redirect_ticket($request_id);
        }


        // ----------------------------------------------------
        // Expert reply to user
        // ----------------------------------------------------

        if ($action === 'user_reply') {

            if ($ticket['status'] === 'closed') {
                throw new Exception(
                    'درخواست بسته شده قابل پاسخ‌گویی نیست.'
                );
            }

            $message = trim(
                $_POST['message'] ?? ''
            );

            if ($message === '') {
                throw new Exception(
                    'متن پاسخ را وارد کنید.'
                );
            }

            $stmt = $db->prepare("
                INSERT INTO ticket_messages
                (
                    request_id,
                    sender_admin_id,
                    sender_username,
                    message,
                    is_internal
                )
                VALUES (?, ?, ?, ?, 0)
            ");

            $stmt->execute([
                $request_id,
                $current_admin_id,
                $current_username,
                $message
            ]);

            $old_status = $ticket['status'];

            $new_status = $old_status;

            if ($old_status === 'waiting_user') {
                $new_status = 'in_progress';
            }

            $stmt = $db->prepare("
                UPDATE service_requests
                SET
                    status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $stmt->execute([
                $new_status,
                $request_id
            ]);

            if ($new_status !== $old_status) {

                $stmt = $db->prepare("
                    INSERT INTO ticket_status_history
                    (
                        request_id,
                        old_status,
                        new_status,
                        changed_by,
                        note
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $request_id,
                    $old_status,
                    $new_status,
                    $current_admin_id,
                    'پاسخ کارشناس به کاربر'
                ]);
            }

            redirect_ticket(
                $request_id,
                'conversation'
            );
        }


        // ----------------------------------------------------
        // Internal note
        // ----------------------------------------------------

        if ($action === 'internal_note') {

            if ($ticket['status'] === 'closed') {
                throw new Exception(
                    'برای درخواست بسته‌شده نمی‌توان یادداشت داخلی ثبت کرد.'
                );
            }

            $message = trim(
                $_POST['internal_message'] ?? ''
            );

            if ($message === '') {
                throw new Exception(
                    'متن یادداشت داخلی را وارد کنید.'
                );
            }

            $stmt = $db->prepare("
                INSERT INTO ticket_messages
                (
                    request_id,
                    sender_admin_id,
                    sender_username,
                    message,
                    is_internal
                )
                VALUES (?, ?, ?, ?, 1)
            ");

            $stmt->execute([
                $request_id,
                $current_admin_id,
                $current_username,
                $message
            ]);

            $stmt = $db->prepare("
                UPDATE service_requests
                SET
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $stmt->execute([
                $request_id
            ]);

            redirect_ticket(
                $request_id,
                'internal-notes'
            );
        }


        // ----------------------------------------------------
        // Waiting for user
        // ----------------------------------------------------

        if ($action === 'waiting_user') {

            if ($ticket['status'] === 'closed') {
                throw new Exception(
                    'درخواست بسته شده قابل تغییر نیست.'
                );
            }

            $note = trim(
                $_POST['waiting_note'] ?? ''
            );

            $old_status = $ticket['status'];

            if ($old_status === 'waiting_user') {
                redirect_ticket($request_id);
            }

            $stmt = $db->prepare("
                UPDATE service_requests
                SET
                    status = 'waiting_user',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $stmt->execute([
                $request_id
            ]);

            $stmt = $db->prepare("
                INSERT INTO ticket_status_history
                (
                    request_id,
                    old_status,
                    new_status,
                    changed_by,
                    note
                )
                VALUES (?, ?, 'waiting_user', ?, ?)
            ");

            $stmt->execute([
                $request_id,
                $old_status,
                $current_admin_id,
                $note !== ''
                    ? $note
                    : 'در انتظار پاسخ کاربر'
            ]);

            redirect_ticket($request_id);
        }


        // ----------------------------------------------------
        // Close ticket
        // ----------------------------------------------------

        if ($action === 'close_ticket') {

            if ($ticket['status'] === 'closed') {
                redirect_ticket($request_id);
            }

            $resolution_note = trim(
                $_POST['resolution_note'] ?? ''
            );

            if (mb_strlen($resolution_note) < 5) {
                throw new Exception(
                    'لطفاً نتیجه و توضیح حل درخواست را کامل وارد کنید.'
                );
            }

            $now = date('Y-m-d H:i:s');

            $resolved_at = !empty(
                $ticket['resolved_at']
            )
                ? $ticket['resolved_at']
                : $now;

            $db->beginTransaction();

            $stmt = $db->prepare("
                UPDATE service_requests
                SET
                    status = 'closed',
                    resolution_note = ?,
                    resolved_at = ?,
                    closed_at = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $stmt->execute([
                $resolution_note,
                $resolved_at,
                $now,
                $request_id
            ]);

            $stmt = $db->prepare("
                INSERT INTO ticket_status_history
                (
                    request_id,
                    old_status,
                    new_status,
                    changed_by,
                    note
                )
                VALUES (?, ?, 'closed', ?, ?)
            ");

            $stmt->execute([
                $request_id,
                $ticket['status'],
                $current_admin_id,
                'درخواست توسط کارشناس حل و بسته شد'
            ]);

            $stmt = $db->prepare("
                INSERT INTO ticket_messages
                (
                    request_id,
                    sender_admin_id,
                    sender_username,
                    message,
                    is_internal
                )
                VALUES (?, ?, ?, ?, 0)
            ");

            $stmt->execute([
                $request_id,
                $current_admin_id,
                $current_username,
                "نتیجه رسیدگی:\n\n" .
                $resolution_note
            ]);

            $db->commit();

            header(
                'Location: ticket-manage.php?id=' .
                $request_id .
                '&closed=1#resolution'
            );

            exit;
        }

    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $error_message = $e->getMessage();
    }
}


// ============================================================
// Refresh ticket after POST
// ============================================================

$stmt = $db->prepare("
    SELECT
        sr.*,
        a.username AS assigned_username
    FROM service_requests sr
    LEFT JOIN admins a
        ON a.id = sr.assigned_to
    WHERE sr.id = ?
    LIMIT 1
");

$stmt->execute([
    $request_id
]);

$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: tickets.php');
    exit;
}


// ============================================================
// Mark incoming visible messages as read
// ============================================================

if ($current_admin_id > 0) {

    try {

        $stmt = $db->prepare("
            SELECT tm.id
            FROM ticket_messages tm
            WHERE tm.request_id = ?
              AND tm.is_internal = 0
              AND (
                    tm.sender_admin_id IS NULL
                    OR tm.sender_admin_id != ?
              )
        ");

        $stmt->execute([
            $request_id,
            $current_admin_id
        ]);

        $message_ids = $stmt->fetchAll(
            PDO::FETCH_COLUMN
        );

        if ($message_ids) {

            $read_stmt = $db->prepare("
                INSERT OR IGNORE INTO
                    ticket_message_reads
                (
                    message_id,
                    admin_id
                )
                VALUES (?, ?)
            ");

            foreach ($message_ids as $message_id) {

                $read_stmt->execute([
                    (int)$message_id,
                    $current_admin_id
                ]);
            }
        }

    } catch (Throwable $e) {
        // Read tracking must never prevent ticket management.
    }
}


// ============================================================
// Success message
// ============================================================

$success_message = '';

if (
    isset($_GET['closed']) &&
    $_GET['closed'] === '1'
) {
    $success_message =
        'درخواست با موفقیت حل و بسته شد.';
}


// ============================================================
// Request types
// ============================================================

$stmt = $db->prepare("
    SELECT rt.name
    FROM service_request_types srt
    INNER JOIN request_types rt
        ON rt.id = srt.request_type_id
    WHERE srt.request_id = ?
    ORDER BY rt.name
");

$stmt->execute([
    $request_id
]);

$request_types = $stmt->fetchAll();


// ============================================================
// User-visible conversation
// ============================================================

$stmt = $db->prepare("
    SELECT
        tm.*,
        CASE
            WHEN tm.sender_admin_id IS NULL
                THEN 1
            ELSE 0
        END AS is_user_message
    FROM ticket_messages tm
    WHERE tm.request_id = ?
      AND tm.is_internal = 0
    ORDER BY tm.created_at ASC, tm.id ASC
");

$stmt->execute([
    $request_id
]);

$conversation = $stmt->fetchAll();


// ============================================================
// Internal notes
// ============================================================

$stmt = $db->prepare("
    SELECT *
    FROM ticket_messages
    WHERE request_id = ?
      AND is_internal = 1
    ORDER BY created_at ASC, id ASC
");

$stmt->execute([
    $request_id
]);

$internal_notes = $stmt->fetchAll();


// ============================================================
// Status history
// ============================================================

$stmt = $db->prepare("
    SELECT
        tsh.*,
        a.username AS admin_username
    FROM ticket_status_history tsh
    LEFT JOIN admins a
        ON a.id = tsh.changed_by
    WHERE tsh.request_id = ?
    ORDER BY tsh.created_at DESC, tsh.id DESC
");

$stmt->execute([
    $request_id
]);

$status_history = $stmt->fetchAll();


// ============================================================
// Assignment history
// ============================================================

$stmt = $db->prepare("
    SELECT
        ta.*,
        fa.username AS from_username,
        toa.username AS to_username,
        ba.username AS by_username
    FROM ticket_assignments ta
    LEFT JOIN admins fa
        ON fa.id = ta.from_admin_id
    LEFT JOIN admins toa
        ON toa.id = ta.to_admin_id
    LEFT JOIN admins ba
        ON ba.id = ta.assigned_by
    WHERE ta.request_id = ?
    ORDER BY ta.created_at DESC, ta.id DESC
");

$stmt->execute([
    $request_id
]);

$assignment_history = $stmt->fetchAll();


// ============================================================
// Attachments
// ============================================================

$stmt = $db->prepare("
    SELECT *
    FROM ticket_attachments
    WHERE request_id = ?
    ORDER BY created_at ASC, id ASC
");

$stmt->execute([
    $request_id
]);

$attachments = $stmt->fetchAll();


// ============================================================
// Admin list
// ============================================================

$stmt = $db->query("
    SELECT
        id,
        username
    FROM admins
    ORDER BY username
");

$admins = $stmt->fetchAll();


// ============================================================
// Portal mode is false: admin page
// ============================================================

$portal_mode = false;

require_once __DIR__ . '/includes/header.php';

?>

<div class="ticket-manage-page">

    <!-- =====================================================
         Header
    ====================================================== -->

    <div class="ticket-manage-header">

        <div>

            <div class="ticket-breadcrumb">

                <a href="tickets.php">
                    درخواست‌ها
                </a>

                <span>←</span>

                <span>
                    مدیریت درخواست
                </span>

            </div>

            <h1>
                <?php echo h($ticket['subject']); ?>
            </h1>

            <div class="ticket-header-meta">

                <span class="tracking-number">
                    <?php echo h($ticket['tracking_number']); ?>
                </span>

                <span class="status-badge <?php echo h(status_class($ticket['status'])); ?>">
                    <?php echo h(status_label($ticket['status'])); ?>
                </span>

                <span class="priority-badge <?php echo h(priority_class($ticket['priority'])); ?>">
                    <?php echo h(priority_label($ticket['priority'])); ?>
                </span>

                <?php if (!empty($ticket['assigned_username'])): ?>

                    <span class="assigned-badge">
                        👤
                        <?php echo h($ticket['assigned_username']); ?>
                    </span>

                <?php else: ?>

                    <span class="unassigned-badge">
                        بدون کارشناس
                    </span>

                <?php endif; ?>

            </div>

        </div>


        <a
            href="tickets.php"
            class="back-button"
        >
            ← بازگشت به درخواست‌ها
        </a>

    </div>


    <!-- =====================================================
         Messages
    ====================================================== -->

    <?php if (!empty($success_message)): ?>

        <div class="alert alert-success">
            ✅ <?php echo h($success_message); ?>
        </div>

    <?php endif; ?>


    <?php if (!empty($error_message)): ?>

        <div class="alert alert-error">
            ⚠️ <?php echo h($error_message); ?>
        </div>

    <?php endif; ?>


    <!-- =====================================================
         Main grid
    ====================================================== -->

    <div class="ticket-grid">

        <!-- =================================================
             Main
        ================================================== -->

        <main class="ticket-main">


            <!-- =================================================
                 Request information
            ================================================== -->

            <section class="manage-card">

                <div class="manage-card-title">

                    <span>📋</span>

                    <h2>
                        اطلاعات درخواست
                    </h2>

                </div>


                <div class="info-grid">

                    <div>
                        <span>درخواست‌کننده</span>
                        <strong>
                            <?php echo h($ticket['fullname']); ?>
                        </strong>
                    </div>


                    <div>
                        <span>نام کاربری</span>
                        <strong>
                            <?php echo h($ticket['requester_username']); ?>
                        </strong>
                    </div>


                    <div>
                        <span>کد پرسنلی</span>
                        <strong>
                            <?php echo h($ticket['personnel_code'] ?: '-'); ?>
                        </strong>
                    </div>


                    <div>
                        <span>واحد</span>
                        <strong>
                            <?php echo h($ticket['department']); ?>
                        </strong>
                    </div>


                    <div>
                        <span>شماره تماس</span>
                        <strong>
                            <?php echo h($ticket['phone'] ?: '-'); ?>
                        </strong>
                    </div>


                    <div>
                        <span>محل</span>
                        <strong>
                            <?php echo h($ticket['location'] ?: '-'); ?>
                        </strong>
                    </div>


                    <div>
                        <span>سیستم مرتبط</span>
                        <strong>
                            <?php echo h($ticket['related_system'] ?: '-'); ?>
                        </strong>
                    </div>


                    <div>
                        <span>نام سیستم / Hostname</span>
                        <strong>
                            <?php echo h($ticket['hostname'] ?: '-'); ?>
                        </strong>
                    </div>


                    <div>
                        <span>IP</span>
                        <strong>
                            <?php echo h($ticket['ip_address'] ?: '-'); ?>
                        </strong>
                    </div>


                    <div>
                        <span>دارایی</span>
                        <strong>
                            <?php echo h($ticket['asset'] ?: '-'); ?>
                        </strong>
                    </div>


                    <div>
                        <span>تاریخ ثبت</span>
                        <strong>
                            <?php echo h(jalali_datetime($ticket['created_at'])); ?>
                        </strong>
                    </div>


                    <div>
                        <span>آخرین تغییر</span>
                        <strong>
                            <?php echo h(jalali_datetime($ticket['updated_at'])); ?>
                        </strong>
                    </div>

                </div>

            </section>


            <!-- =================================================
                 Request type
            ================================================== -->

            <section class="manage-card">

                <div class="manage-card-title">

                    <span>🏷️</span>

                    <h2>
                        نوع درخواست
                    </h2>

                </div>


                <?php if (!empty($request_types)): ?>

                    <div class="type-list">

                        <?php foreach ($request_types as $type): ?>

                            <span class="type-badge">
                                <?php echo h($type['name']); ?>
                            </span>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-box">
                        نوع درخواست ثبت نشده است.
                    </div>

                <?php endif; ?>

            </section>


            <!-- =================================================
                 Description
            ================================================== -->

            <section class="manage-card">

                <div class="manage-card-title">

                    <span>📝</span>

                    <h2>
                        شرح درخواست
                    </h2>

                </div>


                <div class="description-box">
                    <?php echo nl2br(h($ticket['description'])); ?>
                </div>

            </section>


	<!-- =================================================
     Attachments
================================================== -->

<section class="manage-card">

    <div class="manage-card-title">

        <span>📎</span>

        <h2>
            پیوست‌ها
        </h2>

    </div>


    <?php if (!empty($attachments)): ?>

        <div class="attachment-list">

            <?php foreach ($attachments as $attachment): ?>

                <?php
                $attachment_name = (string)($attachment['original_name'] ?? 'attachment');
                $attachment_name = basename($attachment_name);

                $extension = strtolower(
                    pathinfo($attachment_name, PATHINFO_EXTENSION)
                );

                $format = $extension !== ''
                    ? strtoupper($extension)
                    : 'FILE';

                $size = (int)($attachment['file_size'] ?? 0);

                if ($size >= 1024 * 1024) {
                    $size_text = number_format(
                        $size / (1024 * 1024),
                        2
                    ) . ' MB';
                } elseif ($size >= 1024) {
                    $size_text = number_format(
                        $size / 1024,
                        1
                    ) . ' KB';
                } else {
                    $size_text = $size . ' B';
                }

                $download_url =
                    'download-attachment.php?id=' .
                    (int)$attachment['id'];
                ?>

                <div class="attachment-item">

                    <div class="attachment-icon">
                        📄
                    </div>


                    <div class="attachment-info">

                        <a
                            class="attachment-name"
                            href="<?php echo h($download_url); ?>"
                            title="دانلود فایل"
                        >
                            <?php echo h($attachment_name); ?>
                        </a>


                        <div class="attachment-meta">

                            <span class="attachment-format">
                                <?php echo h($format); ?>
                            </span>

                            <span>
                                <?php echo h($size_text); ?>
                            </span>

                            <span>
                                <?php
                                echo h(
                                    jalali_datetime(
                                        $attachment['created_at']
                                    )
                                );
                                ?>
                            </span>

                        </div>

                    </div>


                    <a
                        class="attachment-download"
                        href="<?php echo h($download_url); ?>"
                        title="دانلود فایل"
                    >
                        ⬇ دانلود
                    </a>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty-box">
            این درخواست پیوستی ندارد.
        </div>

    <?php endif; ?>

</section>

            <!-- =================================================
                 Conversation
            ================================================== -->

            <section
                class="manage-card"
                id="conversation"
            >

                <div class="manage-card-title">

                    <span>💬</span>

                    <h2>
                        گفتگو با کاربر
                    </h2>

                </div>


                <?php if (!empty($conversation)): ?>

                    <div class="conversation-list">

                        <?php foreach ($conversation as $message): ?>

                            <?php
                            $is_user =
                                (int)$message['is_user_message'] === 1;
                            ?>

                            <div
                                class="conversation-message <?php
                                    echo $is_user
                                        ? 'user-message'
                                        : 'expert-message';
                                ?>"
                            >

                                <div class="message-head">

                                    <strong>

                                        <?php
                                        echo $is_user
                                            ? '👤 ' . h($ticket['fullname'])
                                            : '🛠️ ' . h($message['sender_username']);
                                        ?>

                                    </strong>

                                    <span>
                                        <?php
                                        echo h(
                                            jalali_datetime(
                                                $message['created_at']
                                            )
                                        );
                                        ?>
                                    </span>

                                </div>


                                <div class="message-body">
                                    <?php
                                    echo ticket_message_html(
                                        $message['message']
                                    );
                                    ?>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-box">
                        هنوز گفتگویی برای این درخواست ثبت نشده است.
                    </div>

                <?php endif; ?>


                <?php if ($ticket['status'] !== 'closed'): ?>

                    <form
                        method="post"
                        class="message-form"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="user_reply"
                        >

                        <input
                            type="hidden"
                            name="request_id"
                            value="<?php echo (int)$request_id; ?>"
                        >


                        <label>
                            پاسخ به کاربر
                        </label>


                        <div class="ticket-rich-editor">

                            <div class="ticket-editor-toolbar">

                                <button
                                    type="button"
                                    class="editor-btn editor-bold"
                                    data-command="bold"
                                    title="پررنگ"
                                >
                                    <strong>B</strong>
                                </button>

                                <button
                                    type="button"
                                    class="editor-btn editor-italic"
                                    data-command="italic"
                                    title="کج"
                                >
                                    <em>I</em>
                                </button>

                                <button
                                    type="button"
                                    class="editor-btn editor-underline"
                                    data-command="underline"
                                    title="زیرخط"
                                >
                                    <u>U</u>
                                </button>

                                <span class="editor-separator"></span>

                                <button
                                    type="button"
                                    class="editor-btn"
                                    data-command="insertUnorderedList"
                                    title="لیست نقطه‌ای"
                                >
                                    •☰
                                </button>

                                <button
                                    type="button"
                                    class="editor-btn"
                                    data-command="insertOrderedList"
                                    title="لیست شماره‌ای"
                                >
                                    1☰
                                </button>

                                <span class="editor-separator"></span>

                                <button
                                    type="button"
                                    class="editor-btn"
                                    data-command="justifyRight"
                                    title="راست‌چین"
                                >
                                    ⇥
                                </button>

                                <button
                                    type="button"
                                    class="editor-btn"
                                    data-command="justifyLeft"
                                    title="چپ‌چین"
                                >
                                    ⇤
                                </button>

                                <button
                                    type="button"
                                    class="editor-btn"
                                    data-command="justifyCenter"
                                    title="وسط‌چین"
                                >
                                    ≡
                                </button>

                                <span class="editor-separator"></span>

                                <button
                                    type="button"
                                    class="editor-btn"
                                    id="ticket-editor-link"
                                    title="افزودن لینک"
                                >
                                    🔗
                                </button>

                                <button
                                    type="button"
                                    class="editor-btn"
                                    id="ticket-editor-emoji"
                                    title="ایموجی"
                                >
                                    😊
                                </button>

                                <div
                                    class="ticket-emoji-panel"
                                    id="ticket-emoji-panel"
                                >
                                    <button type="button">😀</button>
                                    <button type="button">😃</button>
                                    <button type="button">😄</button>
                                    <button type="button">😁</button>
                                    <button type="button">😂</button>
                                    <button type="button">🤣</button>
                                    <button type="button">😊</button>
                                    <button type="button">🙂</button>
                                    <button type="button">😉</button>
                                    <button type="button">😍</button>
                                    <button type="button">🥰</button>
                                    <button type="button">😘</button>
                                    <button type="button">😎</button>
                                    <button type="button">🤔</button>
                                    <button type="button">😐</button>
                                    <button type="button">😕</button>
                                    <button type="button">😢</button>
                                    <button type="button">😭</button>
                                    <button type="button">😡</button>
                                    <button type="button">😱</button>
                                    <button type="button">👍</button>
                                    <button type="button">👎</button>
                                    <button type="button">👏</button>
                                    <button type="button">🙏</button>
                                    <button type="button">❤️</button>
                                    <button type="button">💔</button>
                                    <button type="button">⭐</button>
                                    <button type="button">✅</button>
                                    <button type="button">❌</button>
                                    <button type="button">⚠️</button>
                                    <button type="button">💡</button>
                                    <button type="button">📌</button>
                                    <button type="button">🎯</button>
                                </div>

                                <span class="editor-separator"></span>

                                <button
                                    type="button"
                                    class="editor-btn"
                                    data-command="undo"
                                    title="بازگشت"
                                >
                                    ↶
                                </button>

                                <button
                                    type="button"
                                    class="editor-btn"
                                    data-command="redo"
                                    title="انجام دوباره"
                                >
                                    ↷
                                </button>

                                <button
                                    type="button"
                                    class="editor-btn"
                                    data-command="removeFormat"
                                    title="پاک کردن فرمت"
                                >
                                    ✕
                                </button>

                            </div>


                            <div
                                id="ticket-message-editor"
                                class="ticket-message-editor"
                                contenteditable="true"
                                dir="rtl"
                                data-placeholder="پاسخ یا توضیح مورد نیاز برای کاربر را وارد کنید..."
                            ></div>


                            <textarea
                                name="message"
                                id="ticket-message-value"
                                class="ticket-message-hidden"
                                aria-hidden="true"
                                tabindex="-1"
                                required
                            ></textarea>

                        </div>


                        <button
                            type="submit"
                            class="primary-button"
                        >
                            💬 ارسال پاسخ به کاربر
                        </button>

                    </form>

                <?php else: ?>

                    <div class="closed-info">
                        این درخواست بسته شده و امکان ارسال پاسخ جدید وجود ندارد.
                    </div>

                <?php endif; ?>

            </section>


            <!-- =================================================
                 Internal notes
            ================================================== -->

            <section
                class="manage-card internal-card"
                id="internal-notes"
            >

                <div class="manage-card-title">

                    <span>🔒</span>

                    <h2>
                        یادداشت داخلی کارشناسان
                    </h2>

                </div>


                <div class="internal-warning">
                    🔒 این بخش فقط برای کارشناسان قابل مشاهده است و هیچ‌یک از
                    مطالب آن برای کاربر نمایش داده نمی‌شود.
                </div>


                <?php if (!empty($internal_notes)): ?>

                    <div class="conversation-list">

                        <?php foreach ($internal_notes as $note): ?>

                            <div class="conversation-message internal-message">

                                <div class="message-head">

                                    <strong>
                                        🔒
                                        <?php echo h($note['sender_username']); ?>
                                    </strong>

                                    <span>
                                        <?php
                                        echo h(
                                            jalali_datetime(
                                                $note['created_at']
                                            )
                                        );
                                        ?>
                                    </span>

                                </div>


                                <div class="message-body">

                                    <?php
                                    echo nl2br(
                                        h($note['message'])
                                    );
                                    ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-box">
                        هنوز یادداشت داخلی ثبت نشده است.
                    </div>

                <?php endif; ?>


                <?php if ($ticket['status'] !== 'closed'): ?>

                    <form
                        method="post"
                        class="message-form"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="internal_note"
                        >

                        <input
                            type="hidden"
                            name="request_id"
                            value="<?php echo (int)$request_id; ?>"
                        >


                        <label>
                            یادداشت داخلی جدید
                        </label>


                        <textarea
                            name="internal_message"
                            rows="4"
                            placeholder="یادداشت مخصوص کارشناسان..."
                            required
                        ></textarea>


                        <button
                            type="submit"
                            class="secondary-button"
                        >
                            🔒 ثبت یادداشت داخلی
                        </button>

                    </form>

                <?php endif; ?>

            </section>


            <!-- =================================================
                 Resolution
            ================================================== -->

            <section
                class="manage-card resolution-card"
                id="resolution"
            >

                <div class="manage-card-title">

                    <span>✅</span>

                    <h2>
                        نتیجه و بستن درخواست
                    </h2>

                </div>


                <?php if ($ticket['status'] === 'closed'): ?>

                    <div class="closed-result">

                        <div class="closed-result-title">
                            ✅ این درخواست بسته شده است.
                        </div>


                        <div class="closed-result-meta">

                            بسته شده در:

                            <?php
                            echo h(
                                jalali_datetime(
                                    $ticket['closed_at']
                                )
                            );
                            ?>

                        </div>


                        <div class="resolution-text">

                            <?php
                            echo nl2br(
                                h(
                                    $ticket['resolution_note']
                                    ?: 'نتیجه‌ای ثبت نشده است.'
                                )
                            );
                            ?>

                        </div>

                    </div>

                <?php else: ?>

                    <div class="resolution-warning">

                        قبل از بستن درخواست، نتیجه کار و توضیح نهایی را
                        برای ثبت در سوابق وارد کنید. این توضیح برای کاربر
                        نیز قابل مشاهده خواهد بود.

                    </div>


                    <form method="post">

                        <input
                            type="hidden"
                            name="action"
                            value="close_ticket"
                        >

                        <input
                            type="hidden"
                            name="request_id"
                            value="<?php echo (int)$request_id; ?>"
                        >


                        <label class="resolution-label">

                            نتیجه و توضیح حل درخواست

                            <span>*</span>

                        </label>


                        <textarea
                            name="resolution_note"
                            class="resolution-textarea"
                            placeholder="مثلاً: مشکل چاپگر بررسی شد، درایور اصلاح و تست چاپ با موفقیت انجام شد."
                            required
                            minlength="5"
                        ></textarea>


                        <button
                            type="submit"
                            class="close-ticket-button"
                            onclick="return confirm('آیا از حل شدن و بستن این درخواست مطمئن هستید؟');"
                        >
                            ✅ حل شد و بستن درخواست
                        </button>

                    </form>

                <?php endif; ?>

            </section>


            <!-- =================================================
                 Status history
            ================================================== -->

            <section class="manage-card">

                <div class="manage-card-title">

                    <span>🕘</span>

                    <h2>
                        تاریخچه وضعیت
                    </h2>

                </div>


                <?php if (!empty($status_history)): ?>

                    <div class="history-list">

                        <?php foreach ($status_history as $history): ?>

                            <div class="history-item">

                                <div class="history-dot"></div>

                                <div class="history-content">

                                    <strong>

                                        <?php
                                        echo h(
                                            $history['admin_username']
                                            ?: 'سیستم'
                                        );
                                        ?>

                                    </strong>


                                    <span>

                                        <?php
                                        echo h(
                                            jalali_datetime(
                                                $history['created_at']
                                            )
                                        );
                                        ?>

                                    </span>


                                    <small>

                                        <?php
                                        if (!empty($history['old_status'])) {
                                            echo h(
                                                status_label(
                                                    $history['old_status']
                                                )
                                            );
                                            echo ' ← ';
                                        }

                                        echo h(
                                            status_label(
                                                $history['new_status']
                                            )
                                        );
                                        ?>

                                    </small>


                                    <?php if (!empty($history['note'])): ?>

                                        <p>
                                            <?php
                                            echo nl2br(
                                                h($history['note'])
                                            );
                                            ?>
                                        </p>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-box">
                        هنوز سابقه‌ای ثبت نشده است.
                    </div>

                <?php endif; ?>

            </section>


            <!-- =================================================
                 Assignment history
            ================================================== -->

            <section class="manage-card">

                <div class="manage-card-title">

                    <span>🔄</span>

                    <h2>
                        تاریخچه ارجاع
                    </h2>

                </div>


                <?php if (!empty($assignment_history)): ?>

                    <div class="history-list">

                        <?php foreach ($assignment_history as $assignment): ?>

                            <div class="history-item">

                                <div class="history-dot assignment-dot"></div>

                                <div class="history-content">

                                    <strong>

                                        <?php
                                        echo h(
                                            $assignment['by_username']
                                            ?: 'سیستم'
                                        );
                                        ?>

                                    </strong>


                                    <span>

                                        <?php
                                        echo h(
                                            jalali_datetime(
                                                $assignment['created_at']
                                            )
                                        );
                                        ?>

                                    </span>


                                    <small>

                                        <?php
                                        echo h(
                                            $assignment['from_username']
                                            ?: 'بدون کارشناس'
                                        );

                                        echo ' ← ';

                                        echo h(
                                            $assignment['to_username']
                                            ?: 'نامشخص'
                                        );
                                        ?>

                                    </small>


                                    <?php if (!empty($assignment['note'])): ?>

                                        <p>
                                            <?php
                                            echo nl2br(
                                                h($assignment['note'])
                                            );
                                            ?>
                                        </p>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-box">
                        هنوز ارجاعی برای این درخواست ثبت نشده است.
                    </div>

                <?php endif; ?>

            </section>

        </main>


        <!-- =================================================
             Sidebar
        ================================================== -->

        <aside class="ticket-sidebar">


            <!-- =================================================
                 Assignment
            ================================================== -->

            <?php if ($ticket['status'] !== 'closed'): ?>

                <section class="manage-card">

                    <div class="manage-card-title">

                        <span>👤</span>

                        <h2>
                            کارشناس مسئول
                        </h2>

                    </div>


                    <?php if (!empty($ticket['assigned_username'])): ?>

                        <div class="current-assignee">

                            <span>
                                کارشناس فعلی
                            </span>

                            <strong>
                                👤
                                <?php echo h($ticket['assigned_username']); ?>
                            </strong>

                        </div>

                    <?php else: ?>

                        <div class="current-assignee unassigned">

                            <span>
                                وضعیت
                            </span>

                            <strong>
                                بدون کارشناس
                            </strong>

                        </div>

                    <?php endif; ?>


                    <form
                        method="post"
                        class="side-form"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="assign_ticket"
                        >

                        <input
                            type="hidden"
                            name="request_id"
                            value="<?php echo (int)$request_id; ?>"
                        >


                        <label>
                            ارجاع به
                        </label>


                        <select
                            name="assigned_to"
                            required
                        >

                            <option value="">
                                انتخاب کارشناس
                            </option>

                            <?php foreach ($admins as $admin): ?>

                                <option
                                    value="<?php echo (int)$admin['id']; ?>"
                                    <?php
                                    echo (
                                        (int)$ticket['assigned_to']
                                        === (int)$admin['id']
                                    )
                                        ? 'selected'
                                        : '';
                                    ?>
                                >
                                    <?php echo h($admin['username']); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>


                        <label>
                            توضیح ارجاع
                        </label>


                        <textarea
                            name="assignment_note"
                            rows="3"
                            placeholder="اختیاری"
                        ></textarea>


                        <button
                            type="submit"
                            class="primary-button full-width"
                        >
                            🔄 ثبت ارجاع
                        </button>

                    </form>

                </section>

            <?php endif; ?>


            <!-- =================================================
                 Status
            ================================================== -->

            <?php if ($ticket['status'] !== 'closed'): ?>

                <section class="manage-card">

                    <div class="manage-card-title">

                        <span>⚙️</span>

                        <h2>
                            وضعیت درخواست
                        </h2>

                    </div>


                    <form
                        method="post"
                        class="side-form"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="change_status"
                        >

                        <input
                            type="hidden"
                            name="request_id"
                            value="<?php echo (int)$request_id; ?>"
                        >


                        <label>
                            وضعیت
                        </label>


                        <select
                            name="status"
                            required
                        >

                            <option
                                value="new"
                                <?php
                                echo $ticket['status'] === 'new'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                جدید
                            </option>


                            <option
                                value="assigned"
                                <?php
                                echo $ticket['status'] === 'assigned'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                ارجاع شده
                            </option>


                            <option
                                value="in_progress"
                                <?php
                                echo $ticket['status'] === 'in_progress'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                در حال بررسی
                            </option>


                            <option
                                value="waiting_user"
                                <?php
                                echo $ticket['status'] === 'waiting_user'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                منتظر پاسخ کاربر
                            </option>


                            <option
                                value="cancelled"
                                <?php
                                echo $ticket['status'] === 'cancelled'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                لغو شده
                            </option>

                        </select>


                        <button
                            type="submit"
                            class="primary-button full-width"
                        >
                            💾 ذخیره وضعیت
                        </button>

                    </form>

                </section>

            <?php endif; ?>


            <!-- =================================================
                 Priority
            ================================================== -->

            <?php if ($ticket['status'] !== 'closed'): ?>

                <section class="manage-card">

                    <div class="manage-card-title">

                        <span>🚦</span>

                        <h2>
                            اولویت
                        </h2>

                    </div>


                    <form
                        method="post"
                        class="side-form"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="change_priority"
                        >

                        <input
                            type="hidden"
                            name="request_id"
                            value="<?php echo (int)$request_id; ?>"
                        >


                        <label>
                            اولویت درخواست
                        </label>


                        <select
                            name="priority"
                            required
                        >

                            <option
                                value="normal"
                                <?php
                                echo $ticket['priority'] === 'normal'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                عادی
                            </option>


                            <option
                                value="important"
                                <?php
                                echo $ticket['priority'] === 'important'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                مهم
                            </option>


                            <option
                                value="urgent"
                                <?php
                                echo $ticket['priority'] === 'urgent'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                فوری
                            </option>


                            <option
                                value="critical"
                                <?php
                                echo $ticket['priority'] === 'critical'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                بحرانی
                            </option>

                        </select>


                        <button
                            type="submit"
                            class="primary-button full-width"
                        >
                            🚦 ذخیره اولویت
                        </button>

                    </form>

                </section>

            <?php endif; ?>


            <!-- =================================================
                 Waiting for user
            ================================================== -->

            <?php if (
                $ticket['status'] !== 'closed' &&
                $ticket['status'] !== 'waiting_user'
            ): ?>

                <section class="manage-card">

                    <div class="manage-card-title">

                        <span>⏳</span>

                        <h2>
                            انتظار برای کاربر
                        </h2>

                    </div>


                    <div class="waiting-help">

                        اگر برای ادامه بررسی به اطلاعاتی از کاربر
                        نیاز دارید، درخواست را در حالت «منتظر پاسخ کاربر»
                        قرار دهید.

                    </div>


                    <form
                        method="post"
                        class="side-form"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="waiting_user"
                        >

                        <input
                            type="hidden"
                            name="request_id"
                            value="<?php echo (int)$request_id; ?>"
                        >


                        <label>
                            توضیح
                        </label>


                        <textarea
                            name="waiting_note"
                            rows="4"
                            placeholder="چه اطلاعاتی از کاربر مورد نیاز است؟"
                        ></textarea>


                        <button
                            type="submit"
                            class="waiting-button full-width"
                        >
                            ⏳ منتظر پاسخ کاربر
                        </button>

                    </form>

                </section>

            <?php endif; ?>


            <!-- =================================================
                 Timing
            ================================================== -->

            <section class="manage-card quick-info-card">

                <div class="manage-card-title">

                    <span>📊</span>

                    <h2>
                        وضعیت زمانی
                    </h2>

                </div>


                <div class="quick-info">

                    <div>
                        <span>ثبت درخواست</span>
                        <strong>
                            <?php
                            echo h(
                                jalali_datetime(
                                    $ticket['created_at']
                                )
                            );
                            ?>
                        </strong>
                    </div>


                    <div>
                        <span>تخصیص</span>
                        <strong>
                            <?php
                            echo h(
                                jalali_datetime(
                                    $ticket['assigned_at']
                                )
                            );
                            ?>
                        </strong>
                    </div>


                    <div>
                        <span>شروع بررسی</span>
                        <strong>
                            <?php
                            echo h(
                                jalali_datetime(
                                    $ticket['started_at']
                                )
                            );
                            ?>
                        </strong>
                    </div>


                    <div>
                        <span>حل</span>
                        <strong>
                            <?php
                            echo h(
                                jalali_datetime(
                                    $ticket['resolved_at']
                                )
                            );
                            ?>
                        </strong>
                    </div>


                    <div>
                        <span>بسته شدن</span>
                        <strong>
                            <?php
                            echo h(
                                jalali_datetime(
                                    $ticket['closed_at']
                                )
                            );
                            ?>
                        </strong>
                    </div>

                </div>

            </section>

        </aside>

    </div>

</div>


<style>

.ticket-manage-page {
    max-width: 1500px;
    margin: 0 auto;
}

.ticket-manage-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 24px;
}

.ticket-breadcrumb {
    display: flex;
    gap: 8px;
    color: #718096;
    font-size: 13px;
    margin-bottom: 10px;
}

.ticket-breadcrumb a {
    color: #1b4965;
    text-decoration: none;
}

.ticket-breadcrumb a:hover {
    text-decoration: underline;
}

.ticket-manage-header h1 {
    margin: 0 0 12px 0;
    color: #0b1f3a;
    font-size: 25px;
}

.ticket-header-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.tracking-number {
    color: #718096;
    font-size: 13px;
    font-weight: 700;
}

.status-badge,
.priority-badge,
.assigned-badge,
.unassigned-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.status-new {
    background: #edf2f7;
    color: #4a5568;
}

.status-assigned {
    background: #ebf8ff;
    color: #2b6cb0;
}

.status-progress {
    background: #fff8dc;
    color: #8a6800;
}

.status-waiting {
    background: #fff4e5;
    color: #b45309;
}

.status-resolved,
.status-closed {
    background: #e9f8ef;
    color: #18794e;
}

.status-cancelled {
    background: #fdecec;
    color: #b42318;
}

.priority-normal {
    background: #edf8ef;
    color: #24753b;
}

.priority-important {
    background: #fff7df;
    color: #8a6800;
}

.priority-urgent {
    background: #fff0e8;
    color: #b54708;
}

.priority-critical {
    background: #fde8e8;
    color: #b42318;
}

.assigned-badge {
    background: #eef4fb;
    color: #315a82;
}

.unassigned-badge {
    background: #f7f7f7;
    color: #777;
}

.back-button {
    text-decoration: none;
    background: #fff;
    border: 1px solid #dfe6ef;
    color: #0b1f3a;
    padding: 10px 15px;
    border-radius: 8px;
    white-space: nowrap;
}

.back-button:hover {
    background: #f7fafc;
}

.alert {
    padding: 13px 16px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert-success {
    background: #edf9f1;
    border: 1px solid #b9e2c6;
    color: #196b37;
}

.alert-error {
    background: #fff1f1;
    border: 1px solid #e4b7b7;
    color: #a52323;
}

.ticket-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    gap: 20px;
    align-items: start;
}

.ticket-main {
    min-width: 0;
}

.ticket-sidebar {
    position: sticky;
    top: 15px;
}

.manage-card {
    background: #fff;
    border: 1px solid #e5ebf2;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 3px 12px rgba(0,0,0,.035);
}

.manage-card-title {
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid #edf1f5;
    padding-bottom: 14px;
    margin-bottom: 18px;
}

.manage-card-title span {
    font-size: 20px;
}

.manage-card-title h2 {
    margin: 0;
    font-size: 17px;
    color: #0b1f3a;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.info-grid > div {
    background: #f8fafc;
    border-radius: 8px;
    padding: 12px;
}

.info-grid span,
.quick-info span {
    display: block;
    color: #718096;
    font-size: 12px;
    margin-bottom: 6px;
}

.info-grid strong,
.quick-info strong {
    color: #26364a;
    font-size: 13px;
}

.type-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.type-badge {
    background: #edf4fb;
    color: #315a82;
    border-radius: 7px;
    padding: 7px 10px;
    font-size: 13px;
}

.description-box {
    background: #f8fafc;
    border: 1px solid #edf1f5;
    border-radius: 9px;
    padding: 17px;
    line-height: 1.9;
    color: #354052;
    white-space: normal;
}

.empty-box {
    background: #f8fafc;
    border: 1px dashed #dce4ee;
    color: #718096;
    border-radius: 9px;
    padding: 22px;
    text-align: center;
    font-size: 13px;
}

.conversation-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.conversation-message {
    border-radius: 10px;
    padding: 14px;
}

.user-message {
    background: #fff8e7;
    border-right: 4px solid #d99a18;
}

.expert-message {
    background: #f1f7fc;
    border-right: 4px solid #315a82;
}

.internal-message {
    background: #fff8e7;
    border-right: 4px solid #b88300;
}

.message-head {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 9px;
    font-size: 12px;
}

.message-head strong {
    color: #26364a;
}

.message-head span {
    color: #718096;
}

.message-body {
    line-height: 1.9;
    color: #354052;
    font-size: 14px;
}

.message-form,
.side-form {
    margin-top: 18px;
}

.message-form label,
.side-form label,
.resolution-label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #354052;
    margin-bottom: 7px;
}

.message-form textarea,
.side-form textarea,
.side-form select,
.resolution-textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #d8e0e9;
    border-radius: 8px;
    padding: 11px 12px;
    font-family: inherit;
    font-size: 13px;
    resize: vertical;
    background: #fff;
}

.message-form textarea:focus,
.side-form textarea:focus,
.side-form select:focus,
.resolution-textarea:focus {
    outline: none;
    border-color: #6d8eae;
    box-shadow: 0 0 0 3px rgba(49,90,130,.08);
}

.primary-button,
.secondary-button,
.waiting-button,
.close-ticket-button {
    border-radius: 8px;
    padding: 10px 15px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.primary-button {
    margin-top: 10px;
    background: #0b1f3a;
    color: #fff;
    border: 0;
}

.primary-button:hover {
    background: #17385d;
}

.secondary-button {
    margin-top: 10px;
    background: #eef3f8;
    color: #27445f;
    border: 1px solid #d6e0ea;
}

.secondary-button:hover {
    background: #e2eaf2;
}

.waiting-button {
    background: #fff4df;
    color: #9a6200;
    border: 1px solid #efd49f;
}

.waiting-button:hover {
    background: #ffedc8;
}

.close-ticket-button {
    width: 100%;
    margin-top: 14px;
    background: #23824d;
    color: #fff;
    border: 0;
    padding: 13px 16px;
    font-size: 14px;
}

.close-ticket-button:hover {
    background: #1c6d40;
}

.full-width {
    width: 100%;
}

.internal-card {
    border-color: #ead9aa;
}

.internal-warning {
    background: #fff8e7;
    border: 1px solid #f0dda9;
    color: #806000;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 12px;
    margin-bottom: 15px;
}

.resolution-card {
    border: 2px solid #d5e8dc;
}

.resolution-warning {
    background: #f1f9f3;
    color: #386b48;
    border: 1px solid #cfe5d5;
    border-radius: 8px;
    padding: 11px 13px;
    margin-bottom: 15px;
    font-size: 13px;
    line-height: 1.8;
}

.resolution-label span {
    color: #c00000;
}

.resolution-textarea {
    min-height: 150px;
}

.closed-result {
    background: #f1f9f3;
    border: 1px solid #cce4d3;
    border-radius: 10px;
    padding: 17px;
}

.closed-result-title {
    color: #18794e;
    font-weight: 800;
    margin-bottom: 7px;
}

.closed-result-meta {
    color: #718096;
    font-size: 12px;
    margin-bottom: 15px;
}

.resolution-text {
    background: #fff;
    border-radius: 8px;
    padding: 14px;
    line-height: 1.9;
    color: #354052;
}

.history-list {
    display: flex;
    flex-direction: column;
}

.history-item {
    position: relative;
    display: flex;
    gap: 13px;
    padding: 0 0 18px 0;
}

.history-dot {
    flex: 0 0 10px;
    width: 10px;
    height: 10px;
    margin-top: 5px;
    border-radius: 50%;
    background: #315a82;
}

.assignment-dot {
    background: #8a6b00;
}

.history-content {
    min-width: 0;
}

.history-content strong {
    color: #26364a;
    font-size: 13px;
}

.history-content > span {
    color: #718096;
    font-size: 11px;
    margin-right: 8px;
}

.history-content small {
    display: block;
    color: #718096;
    margin-top: 4px;
}

.history-content p {
    background: #f8fafc;
    border-radius: 7px;
    padding: 9px 11px;
    margin: 8px 0 0;
    color: #4a5568;
    font-size: 12px;
    line-height: 1.8;
}

.attachment-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.attachment-item {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    padding: 12px 13px;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.attachment-item:hover {
    background: #f1f5f9;
    border-color: #cbd5e0;
}

.attachment-icon {
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e8f0fe;
    border-radius: 8px;
    font-size: 21px;
}

.attachment-info {
    min-width: 0;
    flex: 1;
}

.attachment-name {
    display: block;
    color: #2b6cb0;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    word-break: break-word;
    line-height: 1.7;
}

.attachment-name:hover {
    color: #1a4f85;
    text-decoration: underline;
}

.attachment-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 7px;
    margin-top: 5px;
    color: #718096;
    font-size: 11px;
    line-height: 1.6;
}

.attachment-meta > span:not(:last-child)::after {
    content: '·';
    margin-right: 7px;
    color: #a0aec0;
}

.attachment-format {
    display: inline-flex;
    align-items: center;
    padding: 2px 6px;
    background: #edf2f7;
    color: #4a5568;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
}

.attachment-download {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 7px 11px;
    background: #3182ce;
    color: #ffffff;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
}

.attachment-download:hover {
    background: #2b6cb0;
    color: #ffffff;
    text-decoration: none;
}
.quick-info {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.quick-info > div {
    background: #f8fafc;
    border-radius: 8px;
    padding: 10px;
}

.current-assignee {
    background: #eef4fb;
    border: 1px solid #dce8f4;
    border-radius: 9px;
    padding: 12px;
}

.current-assignee span {
    display: block;
    color: #718096;
    font-size: 11px;
    margin-bottom: 5px;
}

.current-assignee strong {
    color: #315a82;
    font-size: 13px;
}

.current-assignee.unassigned {
    background: #f8fafc;
    border-color: #e3e7ec;
}

.current-assignee.unassigned strong {
    color: #718096;
}

.waiting-help {
    background: #fff8e7;
    border: 1px solid #f0dda9;
    border-radius: 8px;
    color: #806000;
    padding: 11px;
    font-size: 12px;
    line-height: 1.8;
}

.closed-info {
    background: #f1f9f3;
    border: 1px solid #cce4d3;
    border-radius: 8px;
    padding: 12px;
    color: #386b48;
    font-size: 13px;
}

.muted {
    color: #718096;
    font-size: 13px;
}

@media (max-width: 1050px) {

    .ticket-grid {
        grid-template-columns: 1fr;
    }

    .ticket-sidebar {
        position: static;
    }

}

@media (max-width: 800px) {

    .ticket-manage-header {
        flex-direction: column;
    }

    .info-grid {
        grid-template-columns: 1fr 1fr;
    }

}

@media (max-width: 520px) {

    .info-grid {
        grid-template-columns: 1fr;
    }

    .manage-card {
        padding: 15px;
    }

}

/* ============================================================
   Ticket rich text editor
   ============================================================ */

.ticket-rich-editor {
    position: relative;
    margin-top: 4px;
}

.ticket-editor-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 5px;
    padding: 8px;
    background: #f6f8fb;
    border: 1px solid #d8e0e9;
    border-bottom: 0;
    border-radius: 8px 8px 0 0;
}

.editor-btn {
    width: 34px;
    height: 32px;
    border: 1px solid #d7dee7;
    border-radius: 6px;
    background: #ffffff;
    color: #354052;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all .15s ease;
}

.editor-btn:hover {
    background: #eef4fb;
    border-color: #b9cadc;
    color: #0b1f3a;
}

.editor-btn:active {
    transform: translateY(1px);
}

.editor-separator {
    width: 1px;
    height: 22px;
    background: #d8e0e9;
    margin: 0 3px;
}

.ticket-message-editor {
    min-height: 145px;
    max-height: 360px;
    overflow-y: auto;
    box-sizing: border-box;
    border: 1px solid #d8e0e9;
    border-radius: 0 0 8px 8px;
    padding: 12px 13px;
    background: #ffffff;
    color: #354052;
    font-family: inherit;
    font-size: 13px;
    line-height: 1.9;
    direction: rtl;
    text-align: right;
    outline: none;
}

.ticket-message-editor:focus {
    border-color: #6d8eae;
    box-shadow: 0 0 0 3px rgba(49,90,130,.08);
}

.ticket-message-editor:empty::before {
    content: attr(data-placeholder);
    color: #a0a9b5;
    pointer-events: none;
}

.ticket-message-editor ul,
.ticket-message-editor ol {
    margin-top: 7px;
    margin-bottom: 7px;
    padding-right: 25px;
}

.ticket-message-editor a {
    color: #2b6cb0;
    text-decoration: underline;
}

.ticket-message-hidden {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
}

.ticket-emoji-panel {
    display: none;
    position: absolute;
    z-index: 50;
    top: 48px;
    right: 5px;
    width: 285px;
    max-height: 220px;
    overflow-y: auto;
    padding: 9px;
    background: #ffffff;
    border: 1px solid #d8e0e9;
    border-radius: 9px;
    box-shadow: 0 10px 30px rgba(20,35,55,.15);
    grid-template-columns: repeat(8, 1fr);
    gap: 4px;
}

.ticket-emoji-panel.open {
    display: grid;
}

.ticket-emoji-panel button {
    width: 30px;
    height: 30px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    cursor: pointer;
    font-size: 19px;
}

.ticket-emoji-panel button:hover {
    background: #eef4fb;
}

@media (max-width: 600px) {

    .ticket-editor-toolbar {
        gap: 4px;
    }

    .editor-btn {
        width: 32px;
        height: 30px;
    }

    .ticket-emoji-panel {
        width: 250px;
        grid-template-columns: repeat(7, 1fr);
    }

}

</style>


<script>
(function () {

    'use strict';

    const editor = document.getElementById('ticket-message-editor');
    const hiddenField = document.getElementById('ticket-message-value');
    const emojiButton = document.getElementById('ticket-editor-emoji');
    const emojiPanel = document.getElementById('ticket-emoji-panel');

    if (!editor || !hiddenField) {
        return;
    }


    function syncMessage() {
        hiddenField.value = editor.innerHTML.trim();
    }


    function focusEditor() {
        editor.focus();
    }


    function execCommand(command, value) {

        focusEditor();

        try {
            document.execCommand(
                command,
                false,
                value || null
            );
        } catch (error) {
            console.warn(
                'Ticket editor command failed:',
                command,
                error
            );
        }

        syncMessage();
    }


    document
        .querySelectorAll(
            '.ticket-editor-toolbar [data-command]'
        )
        .forEach(function (button) {

            button.addEventListener(
                'mousedown',
                function (event) {
                    event.preventDefault();
                }
            );

            button.addEventListener(
                'click',
                function () {
                    execCommand(
                        button.getAttribute('data-command')
                    );
                }
            );

        });


    /*
     * Link
     */
    const linkButton =
        document.getElementById('ticket-editor-link');

    if (linkButton) {

        linkButton.addEventListener(
            'mousedown',
            function (event) {
                event.preventDefault();
            }
        );

        linkButton.addEventListener(
            'click',
            function () {

                focusEditor();

                const url = window.prompt(
                    'آدرس لینک را وارد کنید:',
                    'https://'
                );

                if (!url) {
                    return;
                }

                const cleanUrl = url.trim();

                if (
                    !/^(https?:\/\/|mailto:|tel:)/i.test(
                        cleanUrl
                    )
                ) {
                    window.alert(
                        'لینک باید با http:// یا https:// شروع شود.'
                    );
                    return;
                }

                execCommand(
                    'createLink',
                    cleanUrl
                );

            }
        );

    }


    /*
     * Emoji
     */
    if (emojiButton && emojiPanel) {

        emojiButton.addEventListener(
            'mousedown',
            function (event) {
                event.preventDefault();
            }
        );

        emojiButton.addEventListener(
            'click',
            function () {

                emojiPanel.classList.toggle('open');

            }
        );


        emojiPanel
            .querySelectorAll('button')
            .forEach(function (button) {

                button.addEventListener(
                    'mousedown',
                    function (event) {
                        event.preventDefault();
                    }
                );

                button.addEventListener(
                    'click',
                    function () {

                        focusEditor();

                        document.execCommand(
                            'insertText',
                            false,
                            button.textContent
                        );

                        syncMessage();

                        emojiPanel.classList.remove(
                            'open'
                        );

                    }
                );

            });

    }


    /*
     * Close emoji picker when clicking outside.
     */
    document.addEventListener(
        'click',
        function (event) {

            if (
                emojiPanel &&
                emojiPanel.classList.contains('open') &&
                !emojiPanel.contains(event.target) &&
                event.target !== emojiButton
            ) {
                emojiPanel.classList.remove('open');
            }

        }
    );


    /*
     * Keep hidden textarea synchronized.
     */
    editor.addEventListener(
        'input',
        syncMessage
    );

    editor.addEventListener(
        'keyup',
        syncMessage
    );

    editor.addEventListener(
        'paste',
        function () {

            setTimeout(
                syncMessage,
                0
            );

        }
    );


    /*
     * Submit validation.
     */
    const form = editor.closest('form');

    if (form) {

        form.addEventListener(
            'submit',
            function (event) {

                syncMessage();

                const plainText =
                    editor.textContent
                        .replace(/\u00a0/g, ' ')
                        .trim();

                if (plainText === '') {

                    event.preventDefault();

                    window.alert(
                        'متن پاسخ را وارد کنید.'
                    );

                    focusEditor();
                    return false;
                }

            }
        );

    }


    /*
     * Keyboard shortcuts:
     * Ctrl+B / Ctrl+I / Ctrl+U
     */
    editor.addEventListener(
        'keydown',
        function (event) {

            if (
                (event.ctrlKey || event.metaKey) &&
                ['b', 'i', 'u'].includes(
                    event.key.toLowerCase()
                )
            ) {

                event.preventDefault();

                execCommand(
                    event.key.toLowerCase() === 'b'
                        ? 'bold'
                        : event.key.toLowerCase() === 'i'
                            ? 'italic'
                            : 'underline'
                );

            }

        }
    );


    syncMessage();

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
