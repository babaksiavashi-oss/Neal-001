<?php
declare(strict_types=1);

$portal_mode = true;
require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/includes/chat-common.php';

if (!is_user_logged_in()) {
    header('Location: /neal/login.php');
    exit;
}

$myId = (int)($_SESSION['user_id'] ?? 0);
$db = neal_chat_db();
neal_chat_cleanup_expired($db);
$userStmt = $db->prepare('SELECT id, username, fullname, is_active FROM users WHERE id=? LIMIT 1');
$userStmt->execute([$myId]);
$currentUser = $userStmt->fetch();
if (!$currentUser || (int)$currentUser['is_active'] !== 1) {
    header('Location: /neal/login.php');
    exit;
}

if (empty($_SESSION['neal_chat_csrf'])) {
    $_SESSION['neal_chat_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['neal_chat_csrf'];
$page_title = 'پیام‌رسان همکاران';
$page_css = '/neal/css/chat.css';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="neal-chat-page" id="nealChatApp" data-current-user="<?php echo (int)$myId; ?>">
    <section class="chat-welcome">
        <div class="chat-welcome-copy">
            <span class="chat-eyebrow"><span class="chat-eyebrow-dot"></span> ارتباطات داخلی NEAL</span>
            <h1>پیام‌رسان همکاران</h1>
            <p>گفت‌وگوی سریع، امن و ساده با همکاران شرکت</p>
        </div>
        <div class="chat-welcome-icon" aria-hidden="true">✦</div>
    </section>

    <section class="chat-shell" aria-label="پیام‌رسان داخلی">
        <aside class="chat-sidebar">
            <div class="chat-sidebar-head">
                <div>
                    <h2>همکاران</h2>
                    <p id="chatContactCount">در حال دریافت فهرست کاربران…</p>
                </div>
                <span class="chat-sidebar-icon" aria-hidden="true">☷</span>
            </div>
            <label class="chat-search">
                <span aria-hidden="true">⌕</span>
                <input type="search" id="chatContactSearch" placeholder="جست‌وجوی نام یا نام کاربری" autocomplete="off">
                <kbd>/</kbd>
            </label>
            <div class="chat-contact-filter">
                <button type="button" class="is-active" data-filter="all">همه</button>
                <button type="button" data-filter="online">آنلاین</button>
            </div>
            <div class="chat-contact-list" id="chatContactList">
                <div class="chat-loading"><span class="chat-spinner"></span> در حال بارگذاری همکاران…</div>
            </div>
            <div class="chat-sidebar-foot"><span class="chat-secure-dot"></span> پیام‌رسان داخلی سازمان</div>
        </aside>

        <section class="chat-conversation" aria-label="گفت‌وگو">
            <div class="chat-empty-state" id="chatEmptyState">
                <div class="chat-empty-art" aria-hidden="true">
                    <span class="chat-empty-bubble bubble-one">سلام 👋</span>
                    <span class="chat-empty-bubble bubble-two">😊</span>
                    <span class="chat-empty-bubble bubble-three">پیام شما اینجاست</span>
                    <span class="chat-empty-orb">✦</span>
                </div>
                <h2>گفت‌وگو را شروع کنید</h2>
                <p>از فهرست همکاران، یک نفر را انتخاب کنید تا گفت‌وگوی خصوصی شما باز شود.</p>
            </div>

            <div class="chat-active-conversation" id="chatActiveConversation" hidden>
                <header class="chat-conversation-head">
                    <div class="chat-peer-avatar-wrap">
                        <img id="chatPeerAvatar" src="/neal/images/avatars/default.svg" alt="">
                        <span class="chat-peer-presence" id="chatPeerPresence"></span>
                    </div>
                    <div class="chat-peer-info">
                        <strong id="chatPeerName">همکار</strong>
                        <span id="chatPeerStatus">پیام‌رسان داخلی</span>
                    </div>
                    <div class="chat-head-actions">
                        <span class="chat-private-label"><span>🔒</span> گفت‌وگوی خصوصی</span>
                    </div>
                </header>

                <button type="button" class="chat-load-older" id="chatLoadOlder" hidden>نمایش پیام‌های قدیمی‌تر</button>
                <div class="chat-message-list" id="chatMessageList" aria-live="polite">
                    <div class="chat-loading"><span class="chat-spinner"></span> در حال دریافت پیام‌ها…</div>
                </div>

                <div class="chat-expiry-banner">
                    <span class="chat-expiry-icon" aria-hidden="true">⏳</span>
                    <p><strong>توجه به فایل‌های پیوست:</strong> فایل‌های ارسال‌شده فقط ۱۰ روز روی سرور نگهداری و سپس خودکار حذف می‌شوند. دریافت‌کننده باید فایل را در سیستم خود ذخیره کند.</p>
                </div>

                <div class="chat-composer-wrap">
                    <div class="chat-picker-panel" id="chatEmojiPanel" hidden aria-label="انتخاب ایموجی">
                        <button type="button" data-emoji="😊">😊</button><button type="button" data-emoji="😂">😂</button>
                        <button type="button" data-emoji="😍">😍</button><button type="button" data-emoji="❤️">❤️</button>
                        <button type="button" data-emoji="👍">👍</button><button type="button" data-emoji="🙏">🙏</button>
                        <button type="button" data-emoji="👏">👏</button><button type="button" data-emoji="🎉">🎉</button>
                        <button type="button" data-emoji="🤔">🤔</button><button type="button" data-emoji="🔥">🔥</button>
                        <button type="button" data-emoji="🌷">🌷</button><button type="button" data-emoji="💪">💪</button>
                    </div>
                    <div class="chat-picker-panel chat-sticker-panel" id="chatStickerPanel" hidden aria-label="استیکرهای متنی">
                        <button type="button" data-sticker="🌷 روزت بخیر"><span>🌷</span><small>روزت بخیر</small></button>
                        <button type="button" data-sticker="👏 عالی بود"><span>👏</span><small>عالی بود</small></button>
                        <button type="button" data-sticker="🙏 ممنونم"><span>🙏</span><small>ممنونم</small></button>
                        <button type="button" data-sticker="🎉 تبریک می‌گم"><span>🎉</span><small>تبریک</small></button>
                        <button type="button" data-sticker="💪 خسته نباشی"><span>💪</span><small>خسته نباشی</small></button>
                        <button type="button" data-sticker="☕ وقت استراحته"><span>☕</span><small>استراحت</small></button>
                    </div>
                    <form class="chat-composer" id="chatComposer">
                        <button type="button" class="chat-tool-button" id="chatEmojiButton" title="ایموجی" aria-label="انتخاب ایموجی">☺</button>
                        <button type="button" class="chat-tool-button" id="chatStickerButton" title="استیکرهای سبک" aria-label="استیکرهای سبک">✧</button>
                        <button type="button" class="chat-tool-button chat-attach-button" id="chatAttachButton" title="ارسال فایل" aria-label="ارسال فایل">＋</button>
                        <input type="file" id="chatFileInput" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.xls,.xlsx,.csv" hidden>
                        <textarea id="chatMessageInput" rows="1" maxlength="2000" placeholder="پیام خود را بنویسید…" aria-label="متن پیام"></textarea>
                        <button type="submit" class="chat-send-button" id="chatSendButton" aria-label="ارسال پیام"><span>ارسال</span><b>➤</b></button>
                    </form>
                    <div class="chat-composer-foot"><span>Enter برای ارسال · Shift + Enter برای خط جدید</span><span>حداکثر ۲۰۰۰ نویسه</span></div>
                </div>
            </div>
        </section>
    </section>
    <div class="chat-toast" id="chatToast" role="status" aria-live="polite"></div>
</main>

<script>
(function () {
    'use strict';
    const root = document.getElementById('nealChatApp');
    if (!root) return;
    const csrf = <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const myId = Number(root.dataset.currentUser || 0);
    const apiUrl = '/neal/chat-api.php';
    const list = document.getElementById('chatContactList');
    const search = document.getElementById('chatContactSearch');
    const count = document.getElementById('chatContactCount');
    const empty = document.getElementById('chatEmptyState');
    const active = document.getElementById('chatActiveConversation');
    const messagesBox = document.getElementById('chatMessageList');
    const loadOlderButton = document.getElementById('chatLoadOlder');
    const input = document.getElementById('chatMessageInput');
    const sendButton = document.getElementById('chatSendButton');
    const fileInput = document.getElementById('chatFileInput');
    const toast = document.getElementById('chatToast');
    const emojiPanel = document.getElementById('chatEmojiPanel');
    const stickerPanel = document.getElementById('chatStickerPanel');
    let contacts = [];
    let activePeer = null;
    let lastMessageId = 0;
    let currentFilter = 'all';
    let messageBusy = false;
    let toastTimer = null;

    function request(action, values) {
        const body = new URLSearchParams();
        body.set('action', action);
        body.set('csrf', csrf);
        Object.keys(values || {}).forEach(function (key) { body.set(key, String(values[key])); });
        return fetch(apiUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: body })
            .then(function (response) {
                return response.json().catch(function () { throw new Error('پاسخ سرور قابل خواندن نیست.'); }).then(function (data) {
                    if (!response.ok || !data.ok) throw new Error(data.error || 'انجام درخواست ناموفق بود.');
                    return data;
                });
            });
    }

    function showToast(message, isError) {
        toast.textContent = message;
        toast.classList.toggle('is-error', !!isError);
        toast.classList.add('is-visible');
        if (toastTimer) window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () { toast.classList.remove('is-visible'); }, 6500);
    }

    function make(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function relativeTime(value) {
        if (!value) return '';
        const date = new Date(String(value).replace(' ', 'T') + 'Z');
        if (Number.isNaN(date.getTime())) return String(value);
        return date.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
    }

    function sizeLabel(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toLocaleString('fa-IR', { maximumFractionDigits: 1 }) + ' مگابایت';
        if (bytes >= 1024) return (bytes / 1024).toLocaleString('fa-IR', { maximumFractionDigits: 0 }) + ' کیلوبایت';
        return bytes.toLocaleString('fa-IR') + ' بایت';
    }

    function expiryLabel(timestamp) {
        const date = new Date(Number(timestamp) * 1000);
        return date.toLocaleDateString('fa-IR', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function renderContacts() {
        const term = search.value.trim().toLocaleLowerCase();
        list.replaceChildren();
        const filtered = contacts.filter(function (contact) {
            const matchesText = (contact.fullname + ' ' + contact.username + ' ' + contact.user_status).toLocaleLowerCase().includes(term);
            return matchesText && (currentFilter !== 'online' || contact.online);
        });
        count.textContent = contacts.length.toLocaleString('fa-IR') + ' همکار فعال';
        if (!filtered.length) {
            list.appendChild(make('div', 'chat-no-contacts', term ? 'همکاری با این مشخصات پیدا نشد.' : (currentFilter === 'online' ? 'در حال حاضر همکاری آنلاین نیست.' : 'همکار فعالی برای نمایش وجود ندارد.')));
            return;
        }
        filtered.forEach(function (contact) {
            const item = make('button', 'chat-contact' + (activePeer && activePeer.id === contact.id ? ' is-selected' : ''));
            item.type = 'button';
            item.addEventListener('click', function () { openConversation(contact); });
            const avatarWrap = make('span', 'chat-contact-avatar-wrap');
            const avatar = make('img', 'chat-contact-avatar');
            avatar.src = contact.avatar || '/neal/images/avatars/default.svg';
            avatar.alt = '';
            avatar.loading = 'lazy';
            avatar.onerror = function () { avatar.onerror = null; avatar.src = '/neal/images/avatars/default.svg'; };
            avatarWrap.appendChild(avatar);
            avatarWrap.appendChild(make('span', 'chat-contact-presence' + (contact.online ? ' is-online' : '')));
            const details = make('span', 'chat-contact-details');
            const top = make('span', 'chat-contact-top');
            top.appendChild(make('strong', '', contact.fullname));
            if (contact.last_body) top.appendChild(make('time', 'chat-contact-time', relativeTime(contact.last_at || '')));
            details.appendChild(top);
            details.appendChild(make('span', 'chat-contact-preview', contact.last_body || (contact.user_status || '@' + contact.username)));
            item.appendChild(avatarWrap);
            item.appendChild(details);
            if (contact.unread_count > 0) item.appendChild(make('span', 'chat-unread-count', contact.unread_count > 99 ? '99+' : String(contact.unread_count)));
            list.appendChild(item);
        });
    }

    function renderMessage(message) {
        const mine = Number(message.sender_id) === myId;
        const row = make('div', 'chat-message-row' + (mine ? ' is-mine' : ''));
        row.dataset.messageId = String(message.id);
        const bubble = make('div', 'chat-message-bubble' + (message.attachment ? ' has-attachment' : ''));
        if (message.body) bubble.appendChild(make('div', 'chat-message-text', message.body));
        if (message.attachment) {
            const file = message.attachment;
            const card = make('div', 'chat-file-card');
            const extension = (file.name.split('.').pop() || 'FILE').toUpperCase().slice(0, 5);
            card.appendChild(make('span', 'chat-file-type', extension));
            const info = make('span', 'chat-file-info');
            info.appendChild(make('strong', '', file.name));
            info.appendChild(make('small', '', sizeLabel(file.size)));
            card.appendChild(info);
            if (file.available) {
                const link = make('a', 'chat-file-download', 'دریافت');
                link.href = '/neal/chat-download.php?id=' + encodeURIComponent(file.id);
                link.title = 'دانلود فایل پیوست';
                card.appendChild(link);
            } else {
                card.appendChild(make('span', 'chat-file-expired', 'منقضی'));
            }
            bubble.appendChild(card);
            const note = file.available
                ? 'قابل دریافت تا ' + expiryLabel(file.expires_at) + ' · پس از ۱۰ روز حذف می‌شود'
                : 'مهلت ۱۰ روزه تمام شده و فایل از سرور حذف شده است';
            bubble.appendChild(make('div', 'chat-file-expiry-note', note));
        }
        const meta = make('div', 'chat-message-meta');
        meta.appendChild(make('time', '', relativeTime(message.created_at)));
        if (mine) meta.appendChild(make('span', 'chat-message-read', message.read ? '✓✓' : '✓'));
        bubble.appendChild(meta);
        row.appendChild(bubble);
        messagesBox.appendChild(row);
    }

    function scrollMessagesToBottom() {
        messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    function renderMessages(items, replace) {
        if (replace) messagesBox.replaceChildren();
        if (replace && !items.length) {
            const emptyMessages = make('div', 'chat-no-messages');
            emptyMessages.appendChild(make('span', '', '✦'));
            emptyMessages.appendChild(make('strong', '', 'هنوز پیامی ردوبدل نشده'));
            emptyMessages.appendChild(make('p', '', 'اولین پیام را برای ' + activePeer.fullname + ' بفرستید.'));
            messagesBox.appendChild(emptyMessages);
            return;
        }
        items.forEach(function (message) {
            renderMessage(message);
            lastMessageId = Math.max(lastMessageId, Number(message.id) || 0);
        });
        if (items.length) scrollMessagesToBottom();
    }

    async function loadContacts(quiet) {
        try {
            const data = await request('contacts');
            contacts = data.contacts || [];
            renderContacts();
            if (activePeer) {
                const updated = contacts.find(function (c) { return c.id === activePeer.id; });
                if (updated) {
                    activePeer = updated;
                    document.getElementById('chatPeerPresence').classList.toggle('is-online', !!updated.online);
                    document.getElementById('chatPeerStatus').textContent = updated.online ? 'آنلاین' : (updated.user_status || '@' + updated.username);
                }
            }
        } catch (error) {
            if (!quiet) showToast(error.message, true);
        }
    }

    async function loadMessages(replace) {
        if (!activePeer || messageBusy) return;
        const requestedPeerId = activePeer.id;
        messageBusy = true;
        try {
            const data = await request('messages', { peer_id: requestedPeerId, after_id: replace ? 0 : lastMessageId });
            if (!activePeer || activePeer.id !== requestedPeerId) return;
            if (replace) {
                lastMessageId = 0;
                loadOlderButton.hidden = !data.has_older;
                renderMessages(data.messages || [], true);
            } else if ((data.messages || []).length) {
                const noMessages = messagesBox.querySelector('.chat-no-messages');
                if (noMessages) noMessages.remove();
                renderMessages(data.messages, false);
            }
        } catch (error) {
            if (!replace) showToast(error.message, true);
        } finally {
            messageBusy = false;
            if (activePeer && activePeer.id !== requestedPeerId) loadMessages(true);
        }
    }

    async function loadOlderMessages() {
        if (!activePeer || messageBusy) return;
        const firstRow = messagesBox.querySelector('.chat-message-row');
        const beforeId = firstRow ? Number(firstRow.dataset.messageId || 0) : 0;
        if (!beforeId) {
            loadOlderButton.hidden = true;
            return;
        }
        const requestedPeerId = activePeer.id;
        const oldHeight = messagesBox.scrollHeight;
        const oldTop = messagesBox.scrollTop;
        messageBusy = true;
        loadOlderButton.disabled = true;
        try {
            const data = await request('messages', { peer_id: requestedPeerId, before_id: beforeId });
            if (!activePeer || activePeer.id !== requestedPeerId) return;
            if (!(data.messages || []).length) {
                loadOlderButton.hidden = true;
                return;
            }
            const existingNodes = Array.from(messagesBox.childNodes);
            messagesBox.replaceChildren();
            data.messages.forEach(function (message) { renderMessage(message); });
            existingNodes.forEach(function (node) { messagesBox.appendChild(node); });
            messagesBox.scrollTop = messagesBox.scrollHeight - oldHeight + oldTop;
            loadOlderButton.hidden = !data.has_older;
        } catch (error) {
            showToast(error.message, true);
        } finally {
            messageBusy = false;
            loadOlderButton.disabled = false;
            if (activePeer && activePeer.id !== requestedPeerId) loadMessages(true);
        }
    }

    function openConversation(contact) {
        activePeer = contact;
        lastMessageId = 0;
        empty.hidden = true;
        active.hidden = false;
        document.getElementById('chatPeerAvatar').src = contact.avatar || '/neal/images/avatars/default.svg';
        document.getElementById('chatPeerAvatar').onerror = function () { this.onerror = null; this.src = '/neal/images/avatars/default.svg'; };
        document.getElementById('chatPeerName').textContent = contact.fullname;
        document.getElementById('chatPeerStatus').textContent = contact.online ? 'آنلاین' : (contact.user_status || '@' + contact.username);
        document.getElementById('chatPeerPresence').classList.toggle('is-online', !!contact.online);
        renderContacts();
        messagesBox.replaceChildren(make('div', 'chat-loading', 'در حال دریافت پیام‌ها…'));
        emojiPanel.hidden = true;
        stickerPanel.hidden = true;
        loadMessages(true);
        loadContacts(true);
        input.focus();
    }

    async function sendMessage(text) {
        if (!activePeer || !text.trim()) return;
        const requestedPeerId = activePeer.id;
        sendButton.disabled = true;
        try {
            await request('send', { peer_id: requestedPeerId, body: text.trim() });
            if (activePeer && activePeer.id === requestedPeerId) {
                input.value = '';
                resizeInput();
                await loadMessages(false);
            }
            await loadContacts(true);
        } catch (error) {
            showToast(error.message, true);
        } finally {
            sendButton.disabled = false;
            input.focus();
        }
    }

    function resizeInput() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    }

    loadOlderButton.addEventListener('click', loadOlderMessages);
    document.getElementById('chatComposer').addEventListener('submit', function (event) {
        event.preventDefault();
        sendMessage(input.value);
    });
    input.addEventListener('input', resizeInput);
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            document.getElementById('chatComposer').requestSubmit();
        }
    });

    document.getElementById('chatAttachButton').addEventListener('click', function () {
        if (!activePeer) return showToast('ابتدا یک همکار را انتخاب کنید.', true);
        fileInput.click();
    });
    fileInput.addEventListener('change', async function () {
        if (!fileInput.files || !fileInput.files[0] || !activePeer) return;
        const file = fileInput.files[0];
        if (file.size > 10 * 1024 * 1024) {
            showToast('حجم فایل نمی‌تواند بیشتر از ۱۰ مگابایت باشد.', true);
            fileInput.value = '';
            return;
        }
        const allowed = /\.(jpe?g|png|gif|webp|pdf|xls|xlsx|csv)$/i;
        if (!allowed.test(file.name)) {
            showToast('فقط تصاویر، PDF و فایل‌های Excel با پسوند XLS/XLSX/CSV مجاز هستند.', true);
            fileInput.value = '';
            return;
        }
        const form = new FormData();
        form.append('action', 'upload');
        form.append('csrf', csrf);
        form.append('peer_id', String(activePeer.id));
        form.append('attachment', file);
        document.getElementById('chatAttachButton').disabled = true;
        showToast('در حال ارسال فایل…', false);
        try {
            const response = await fetch(apiUrl, { method: 'POST', credentials: 'same-origin', body: form });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'ارسال فایل ناموفق بود.');
            fileInput.value = '';
            if (activePeer && activePeer.id === Number(form.get('peer_id'))) await loadMessages(false);
            await loadContacts(true);
            showToast(data.notice || 'فایل ارسال شد و تا ۱۰ روز روی سرور می‌ماند.', false);
        } catch (error) {
            showToast(error.message, true);
        } finally {
            document.getElementById('chatAttachButton').disabled = false;
        }
    });

    document.getElementById('chatEmojiButton').addEventListener('click', function () {
        emojiPanel.hidden = !emojiPanel.hidden;
        stickerPanel.hidden = true;
    });
    document.getElementById('chatStickerButton').addEventListener('click', function () {
        stickerPanel.hidden = !stickerPanel.hidden;
        emojiPanel.hidden = true;
    });
    emojiPanel.querySelectorAll('[data-emoji]').forEach(function (button) {
        button.addEventListener('click', function () {
            const start = input.selectionStart || 0;
            const end = input.selectionEnd || 0;
            input.value = input.value.slice(0, start) + button.dataset.emoji + input.value.slice(end);
            input.focus();
            input.setSelectionRange(start + button.dataset.emoji.length, start + button.dataset.emoji.length);
            resizeInput();
        });
    });
    stickerPanel.querySelectorAll('[data-sticker]').forEach(function (button) {
        button.addEventListener('click', function () {
            stickerPanel.hidden = true;
            sendMessage(button.dataset.sticker || '');
        });
    });
    search.addEventListener('input', renderContacts);
    document.querySelectorAll('[data-filter]').forEach(function (button) {
        button.addEventListener('click', function () {
            currentFilter = button.dataset.filter || 'all';
            document.querySelectorAll('[data-filter]').forEach(function (b) { b.classList.toggle('is-active', b === button); });
            renderContacts();
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === '/' && !/INPUT|TEXTAREA/.test(document.activeElement.tagName)) {
            event.preventDefault();
            search.focus();
        }
        if (event.key === 'Escape') {
            emojiPanel.hidden = true;
            stickerPanel.hidden = true;
        }
    });

    loadContacts(false);
    window.setInterval(function () {
        if (activePeer) loadMessages(false);
    }, 4000);
    window.setInterval(function () {
        loadContacts(true);
    }, 15000);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
