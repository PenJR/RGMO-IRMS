<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100 gap-3 flex-wrap">
            <div>
                <h2 class="fw-bold mb-1">Notifications</h2>
                <p class="text-muted mb-0">Review alerts and updates sent to your account.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @php
                    $totalUnreadCount = auth()->user()->notifications()->unread()->count();
                    $readCount = collect($notifications->items())->whereNotNull('read_at')->count();
                @endphp
                @if($totalUnreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary">Mark all as read</button>
                    </form>
                @endif
                @if($readCount > 0)
                    <form method="POST" action="{{ route('notifications.delete-read') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">Delete read</button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div
            id="notifications-panel"
            class="card border-0 shadow-sm mb-4"
            data-index-url="{{ route('notifications.index') }}"
            data-read-url-template="{{ route('notifications.read', ['notification' => '__ID__']) }}"
            data-read-all-url="{{ route('notifications.read-all') }}"
            data-csrf-token="{{ csrf_token() }}"
        >
            <div class="card-body p-4">
                <div id="notifications-loading" class="text-center py-4 d-none">
                    <div class="spinner-border text-success" role="status" aria-label="Loading notifications"></div>
                    <p class="text-muted mt-3 mb-0">Loading notifications...</p>
                </div>

                <div id="notifications-error" class="alert alert-danger d-none" role="alert">
                    Unable to load notifications. Please try again.
                </div>

                @if($notifications->count() > 0)
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <p id="notifications-unread-summary" class="text-muted mb-0">
                            {{ $totalUnreadCount }} unread {{ \Illuminate\Support\Str::plural('notification', $totalUnreadCount) }}
                        </p>
                    </div>

                    <div id="notifications-list" class="list-group list-group-flush">
                        @foreach($notifications as $notification)
                            <div class="list-group-item px-0 py-3 border-bottom {{ is_null($notification->read_at) ? 'bg-light' : '' }}">
                                <div class="d-flex justify-content-between gap-3 align-items-start">
                                    <div class="min-w-0">
                                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                            <h3 class="h6 fw-bold mb-0">{{ $notification->title ?? \Illuminate\Support\Str::of($notification->type)->replace('_', ' ')->title() }}</h3>
                                            <span class="badge rounded-pill {{ $notification->read_at ? 'bg-secondary text-white' : 'bg-warning text-dark' }}">
                                                {{ $notification->read_at ? 'Read' : 'Unread' }}
                                            </span>
                                            <span class="badge rounded-pill bg-success-subtle text-success-emphasis text-uppercase">{{ str_replace('_', ' ', $notification->type) }}</span>
                                        </div>
                                        <p class="mb-1 text-dark">{{ $notification->message }}</p>
                                        <div class="d-flex gap-3 flex-wrap text-muted small">
                                            <span>{{ $notification->created_at?->format('M d, Y h:i A') ?? 'Unknown date' }}</span>
                                            @if($notification->sender)
                                                <span>From {{ $notification->sender->name }}</span>
                                            @endif
                                            @if($notification->related_request_id)
                                                <a href="{{ route('requests.show', ['request' => $notification->related_request_id]) }}" class="link-success fw-semibold">Request #{{ $notification->related_request_id }}</a>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end gap-2 flex-shrink-0">
                                        @if(is_null($notification->read_at))
                                            <form method="POST" action="{{ route('notifications.read', ['notification' => $notification->id]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success">Mark read</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('notifications.destroy', ['notification' => $notification->id]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        {{ $notifications->links() }}
                    </div>
                @else
                    <div id="notifications-list" class="list-group list-group-flush"></div>
                    <div id="notifications-empty" class="text-center py-5">
                        <h5 class="mb-2">No notifications yet</h5>
                        <p class="text-muted mb-3">You will receive updates when there are new alerts or system actions.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const panel = document.getElementById('notifications-panel');
                if (!panel) return;

                const list = document.getElementById('notifications-list');
                const loading = document.getElementById('notifications-loading');
                const error = document.getElementById('notifications-error');
                const empty = document.getElementById('notifications-empty');
                const summary = document.getElementById('notifications-unread-summary');
                const badge = document.getElementById('notification-unread-badge');
                const csrfToken = panel.dataset.csrfToken;

                const headers = {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                };

                const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;',
                }[char]));

                const updateUnreadCount = (count) => {
                    if (badge) {
                        badge.textContent = count;
                        badge.classList.toggle('d-none', count <= 0);
                    }

                    if (summary) {
                        summary.textContent = `${count} unread notification${count === 1 ? '' : 's'}`;
                    }
                };

                const setLoading = (isLoading) => {
                    loading?.classList.toggle('d-none', !isLoading);
                    error?.classList.add('d-none');
                };

                const notificationHtml = (notification) => {
                    const read = notification.is_read;
                    const requestLink = notification.related_request_url
                        ? `<a href="${escapeHtml(notification.related_request_url)}" class="link-success fw-semibold">Request #${escapeHtml(notification.related_request_id)}</a>`
                        : '';
                    const sender = notification.sender?.name
                        ? `<span>From ${escapeHtml(notification.sender.name)}</span>`
                        : '';
                    const markRead = read ? '' : `
                        <button type="button" class="btn btn-sm btn-outline-success notification-mark-read" data-notification-id="${notification.id}">
                            Mark read
                        </button>
                    `;

                    return `
                        <div class="list-group-item px-0 py-3 border-bottom ${read ? '' : 'bg-light'}" data-notification-id="${notification.id}">
                            <div class="d-flex justify-content-between gap-3 align-items-start flex-wrap">
                                <div class="min-w-0">
                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                        <h3 class="h6 fw-bold mb-0">${escapeHtml(notification.title)}</h3>
                                        <span class="badge rounded-pill ${read ? 'bg-secondary text-white' : 'bg-warning text-dark'}">${read ? 'Read' : 'Unread'}</span>
                                        <span class="badge rounded-pill bg-success-subtle text-success-emphasis text-uppercase">${escapeHtml(notification.type).replaceAll('_', ' ')}</span>
                                    </div>
                                    <p class="mb-1 text-dark">${escapeHtml(notification.message)}</p>
                                    <div class="d-flex gap-3 flex-wrap text-muted small">
                                        <span>${escapeHtml(notification.created_at_human ?? notification.created_at ?? 'Unknown date')}</span>
                                        ${sender}
                                        ${requestLink}
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end gap-2 flex-shrink-0">
                                    ${markRead}
                                </div>
                            </div>
                        </div>
                    `;
                };

                const renderNotifications = (payload) => {
                    const notifications = payload.data ?? [];
                    updateUnreadCount(payload.meta?.unread_count ?? 0);

                    if (!list) return;

                    if (notifications.length === 0) {
                        list.innerHTML = '';
                        empty?.classList.remove('d-none');
                        return;
                    }

                    empty?.classList.add('d-none');
                    list.innerHTML = notifications.map(notificationHtml).join('');
                };

                const loadNotifications = async () => {
                    setLoading(true);

                    try {
                        const response = await fetch(panel.dataset.indexUrl, { headers });
                        if (!response.ok) throw new Error('Request failed');
                        renderNotifications(await response.json());
                    } catch (e) {
                        error?.classList.remove('d-none');
                    } finally {
                        setLoading(false);
                    }
                };

                const markAsRead = async (notificationId) => {
                    const url = panel.dataset.readUrlTemplate.replace('__ID__', notificationId);
                    const response = await fetch(url, { method: 'POST', headers });
                    if (!response.ok) throw new Error('Unable to mark notification as read');
                    await loadNotifications();
                };

                const markAllAsRead = async () => {
                    const response = await fetch(panel.dataset.readAllUrl, { method: 'POST', headers });
                    if (!response.ok) throw new Error('Unable to mark notifications as read');
                    await loadNotifications();
                };

                list?.addEventListener('click', async (event) => {
                    const button = event.target.closest('.notification-mark-read');
                    if (!button) return;

                    button.disabled = true;
                    try {
                        await markAsRead(button.dataset.notificationId);
                    } catch (e) {
                        error?.classList.remove('d-none');
                        button.disabled = false;
                    }
                });

                document.querySelector('form[action="{{ route('notifications.read-all') }}"]')?.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    try {
                        await markAllAsRead();
                    } catch (e) {
                        error?.classList.remove('d-none');
                    }
                });

                loadNotifications();
            });
        </script>
    @endpush
</x-app-layout>
