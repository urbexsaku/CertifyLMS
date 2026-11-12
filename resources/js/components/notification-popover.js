/**
 * Notification Popover:
 * - 通知一覧の取得・表示
 * - 全件 / 未読タブの切り替え
 * - 通知の既読化・全件既読化
 * - 外側クリック / Esc で閉じる
 */

import { getJson, postJson } from '../utils/fetch-json';

const API_URL = '/api/v1/notifications';

const ERROR_MESSAGES = {
    fetch: '通知を取得できませんでした。時間をおいて再度お試しください。',
    read: '通知を既読にできませんでした。時間をおいて再度お試しください。',
    readAll: '通知をすべて既読にできませんでした。時間をおいて再度お試しください。',
};

function resolveNotificationUrl(notification) {
    const params = notification.route_params ?? {};

    switch (notification.route) {
        case 'chat.show':
            return params.room
                ? `/chat-rooms/${encodeURIComponent(params.room)}`
                : '/notifications';

        case 'qa-board.show':
            return params.thread
                ? `/qa-board/${encodeURIComponent(params.thread)}`
                : '/notifications';

        case 'meetings.index':
            return '/meetings';

        case 'coach.meetings.index':
            return '/coach/meetings';

        case 'meetings.show':
            return params.meeting
                ? `/meetings/${encodeURIComponent(params.meeting)}`
                : '/notifications';

        case 'notifications.show':
            return params.notification
                ? `/notifications/${encodeURIComponent(params.notification)}`
                : '/notifications';

        case 'notifications.index':
        default:
            return '/notifications';
    }
}

export function initNotificationPopover() {
    const root = document.querySelector('[data-notification-popover-root]');

    if (!root) return;

    const trigger = root.querySelector('[data-notification-popover-trigger]');
    const panel = root.querySelector('[data-notification-popover-panel]');
    const tabs = root.querySelectorAll('[data-notification-popover-tab]');
    const badge = root.querySelector('[data-notification-popover-badge]');
    const unreadCount = root.querySelector('[data-notification-popover-unread-count]');
    const markAllButton = root.querySelector('[data-notification-popover-mark-all]');
    const loading = root.querySelector('[data-notification-popover-loading]');
    const empty = root.querySelector('[data-notification-popover-empty]');
    const items = root.querySelector('[data-notification-popover-items]');
    const template = root.querySelector('[data-notification-popover-row-template]');

    if (
        !trigger
        || !panel
        || !badge
        || !unreadCount
        || !markAllButton
        || !loading
        || !empty
        || !items
        || !template
    ) {
        return;
    }

    let activeTab = 'all';
    let unreadTotal = Number(badge.textContent.trim().replace('+', '')) || 0;
    let isOpen = false;
    let isLoading = false;

    function updateUnreadCount(count) {
        unreadTotal = Math.max(0, count);

        const displayCount = unreadTotal > 99 ? '99+' : String(unreadTotal);

        badge.textContent = displayCount;
        unreadCount.textContent = displayCount;

        badge.classList.toggle('hidden', unreadTotal === 0);
        trigger.setAttribute('aria-label', `通知 (${unreadTotal} 件未読)`);
    }

    function setLoading(value) {
        loading.classList.toggle('hidden', !value);
        isLoading = value;
    }

    function showError(message) {
        window.alert(message);
    }

    function setTab(tab) {
        activeTab = tab;

        tabs.forEach((button) => {
            button.setAttribute(
                'aria-selected',
                button.dataset.notificationPopoverTab === tab ? 'true' : 'false'
            );
        });
    }

    function renderNotification(notification) {
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('[data-notification-popover-row]');
        const title = fragment.querySelector('[data-notification-popover-row-title]');
        const message = fragment.querySelector('[data-notification-popover-row-message]');
        const time = fragment.querySelector('[data-notification-popover-row-time]');
        const dot = fragment.querySelector('[data-notification-popover-row-dot]');

        const isUnread = notification.read_at === null;

        row.dataset.notificationId = notification.id;
        row.dataset.unread = String(isUnread);
        row.href = resolveNotificationUrl(notification);

        title.textContent = notification.title ?? '';
        message.textContent = notification.preview ?? '';
        time.textContent = notification.relative_time ?? '';

        dot.classList.toggle('hidden', !isUnread);

        return fragment;
    }

    async function fetchNotifications() {
        if (isLoading) return;

        setLoading(true);
        empty.classList.add('hidden');
        items.replaceChildren();

        try {
            const payload = await getJson(`${API_URL}?tab=${activeTab}`);

            updateUnreadCount(payload.unread_count ?? 0);

            (payload.data ?? []).forEach((notification) => {
                items.appendChild(renderNotification(notification));
            });

            empty.classList.toggle('hidden', items.children.length > 0);
        } catch {
            showError(ERROR_MESSAGES.fetch);
        } finally {
            setLoading(false);
        }
    }

    function openPopover() {
        if (isOpen) return;

        isOpen = true;
        panel.classList.remove('hidden', 'opacity-0', '-translate-y-1');
        panel.classList.add('opacity-100', 'translate-y-0');
        panel.style.display = 'flex';

        trigger.setAttribute('aria-expanded', 'true');

        fetchNotifications();
    }

    function closePopover() {
        if (!isOpen) return;

        isOpen = false;
        panel.classList.add('hidden', 'opacity-0', '-translate-y-1');
        panel.classList.remove('opacity-100', 'translate-y-0');
        panel.style.display = 'none';

        trigger.setAttribute('aria-expanded', 'false');
    }

    trigger.addEventListener('click', (event) => {
        event.stopPropagation();

        if (isOpen) {
            closePopover();
        } else {
            openPopover();
        }
    });

    tabs.forEach((button) => {
        button.addEventListener('click', () => {
            const tab = button.dataset.notificationPopoverTab;

            if (tab === activeTab || isLoading) return;

            setTab(tab);
            fetchNotifications();
        });
    });

    markAllButton.addEventListener('click', async () => {
        if (isLoading || unreadTotal === 0) return;

        markAllButton.disabled = true;

        try {
            await postJson(`${API_URL}/read-all`);

            updateUnreadCount(0);

            if (activeTab === 'unread') {
                items.replaceChildren();
                empty.classList.remove('hidden');
            } else {
                items.querySelectorAll('[data-notification-popover-row]').forEach((row) => {
                    row.dataset.unread = 'false';
                    row.querySelector('[data-notification-popover-row-dot]')
                        ?.classList.add('hidden');
                });
            }
        } catch {
            showError(ERROR_MESSAGES.readAll);
        } finally {
            markAllButton.disabled = false;
        }
    });

    items.addEventListener('click', async (event) => {
        const row = event.target.closest('[data-notification-popover-row]');

        if (!row) return;

        event.preventDefault();

        if (row.dataset.processing === 'true') return;

        const notificationId = row.dataset.notificationId;
        const destination = row.href;
        const wasUnread = row.dataset.unread === 'true';

        row.dataset.processing = 'true';

        try {
            await postJson(
                `${API_URL}/${encodeURIComponent(notificationId)}/read`
            );

            if (wasUnread) {
                updateUnreadCount(unreadTotal - 1);
            }

            if (activeTab === 'unread') {
                row.closest('li')?.remove();
                empty.classList.toggle('hidden', items.children.length > 0);
            } else {
                row.dataset.unread = 'false';
                row.querySelector('[data-notification-popover-row-dot]')
                    ?.classList.add('hidden');
            }

            window.location.href = destination;
        } catch {
            row.dataset.processing = 'false';
            showError(ERROR_MESSAGES.read);
        }
    });

    document.addEventListener('click', (event) => {
        if (isOpen && !root.contains(event.target)) {
            closePopover();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen) {
            closePopover();
            trigger.focus();
        }
    });
}