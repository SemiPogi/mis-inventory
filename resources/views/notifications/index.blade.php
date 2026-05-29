<x-app-layout>
    <x-page-header title="Notifications" subtitle="Your recent alerts and updates.">
        <x-slot name="actions">
            <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                @csrf
                <x-button type="submit" variant="ghost">Mark all as read</x-button>
            </form>
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif

    <x-bento-card :padded="false">
        @if($notifications->isEmpty())
            <x-empty-state icon="bell" title="No notifications" hint="You're all caught up!"/>
        @else
            <ul class="divide-y divide-surface-border">
                @foreach($notifications as $notif)
                    <li class="px-6 py-4 flex items-start gap-4 {{ $notif->isUnread() ? 'bg-primary-50/40' : '' }}">
                        <div class="shrink-0 mt-0.5">
                            @if($notif->isUnread())
                                <span class="block w-2 h-2 rounded-full bg-primary-500 mt-1.5"></span>
                            @else
                                <span class="block w-2 h-2 rounded-full bg-transparent mt-1.5"></span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-ink-heading">{{ $notif->title }}</p>
                            <p class="text-sm text-ink-body mt-0.5">{{ $notif->message }}</p>
                            <p class="text-xs text-ink-muted mt-1">{{ $notif->created_at->diffForHumans() }}</p>
                        </div>
                        @if(!empty($notif->data['url']))
                            <a href="{{ route('notifications.read', $notif) }}"
                               class="shrink-0 text-xs font-medium text-primary-600 hover:text-primary-700 mt-1">
                                View →
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="px-6 py-3">{{ $notifications->links() }}</div>
        @endif
    </x-bento-card>
</x-app-layout>
