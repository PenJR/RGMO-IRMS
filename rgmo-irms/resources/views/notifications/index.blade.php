<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">Notifications</h2>
                <p class="text-muted mb-0">Review alerts and updates sent to your account.</p>
            </div>
            <div class="d-flex gap-2">
                @php
                    $unreadCount = collect($notifications->items())->whereNull('read_at')->count();
                    $readCount = collect($notifications->items())->whereNotNull('read_at')->count();
                @endphp
                @if($unreadCount > 0)
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
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                @if($notifications->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Received</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($notifications as $notification)
                                    <tr class="@if(is_null($notification->read_at)) bg-light @endif">
                                        <td>{{ $notification->message }}</td>
                                        <td>
                                            <span class="badge rounded-pill @if($notification->read_at) bg-secondary text-white @else bg-warning text-dark @endif">
                                                {{ $notification->read_at ? 'Read' : 'Unread' }}
                                            </span>
                                        </td>
                                        <td>{{ $notification->created_at?->format('M d, Y H:i') ?? 'Unknown' }}</td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
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
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $notifications->links() }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <h5 class="mb-2">No notifications yet</h5>
                        <p class="text-muted mb-3">You will receive updates when there are new alerts or system actions.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
