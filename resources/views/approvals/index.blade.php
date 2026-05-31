<x-app-layout>
    <x-page-header title="Approvals" subtitle="Pending receive and release requests awaiting your approval"/>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- ── Pending Receives ─────────────────────────────────────── --}}
    <x-bento-card :padded="false" class="mb-6">
        <div class="px-6 py-4 border-b border-surface-border">
            <h2 class="text-sm font-semibold text-ink-heading">
                Pending Receives
                <span class="text-ink-muted">({{ $pendingReceives->count() }})</span>
            </h2>
        </div>

        @if($pendingReceives->isEmpty())
            <x-empty-state icon="inbox" title="No pending receives" hint="All receive submissions have been reviewed."/>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-page/50">
                        <tr class="border-b border-surface-border">
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Item</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Qty</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Unit</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Received From</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Submitted By</th>
                            @if(auth()->user()->isAdmin())
                                <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Dept</th>
                            @endif
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Date</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-border">
                        @foreach($pendingReceives as $tx)
                            <tr x-data="{ rejectOpen: false }">
                                <td class="px-6 py-3 font-medium text-ink-heading">{{ $tx->item_name_snapshot }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->qty }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->unit }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->received_from ?? '—' }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->receivedBy?->name ?? '—' }}</td>
                                @if(auth()->user()->isAdmin())
                                    <td class="px-6 py-3 text-ink-muted text-xs">{{ $tx->department?->name ?? '—' }}</td>
                                @endif
                                <td class="px-6 py-3 text-ink-body">{{ $tx->date_received }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-2 min-w-[120px]">
                                        {{-- Approve --}}
                                        <form method="POST" action="{{ route('approvals.approve', $tx) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-button type="submit" variant="primary" class="w-full">Approve</x-button>
                                        </form>

                                        {{-- Reject toggle --}}
                                        <x-button type="button" variant="ghost" class="w-full" @click="rejectOpen = !rejectOpen">
                                            Reject
                                        </x-button>

                                        {{-- Inline reject form --}}
                                        <div x-show="rejectOpen" x-cloak class="mt-1">
                                            <form method="POST" action="{{ route('approvals.reject', $tx) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-textarea name="notes" rows="2" required placeholder="Reason for rejection…" class="mb-2 w-full"/>
                                                <x-button type="submit" variant="danger" class="w-full">Confirm Reject</x-button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-bento-card>

    {{-- ── Pending Releases ─────────────────────────────────────── --}}
    <x-bento-card :padded="false">
        <div class="px-6 py-4 border-b border-surface-border">
            <h2 class="text-sm font-semibold text-ink-heading">
                Pending Releases
                <span class="text-ink-muted">({{ $pendingReleases->count() }})</span>
            </h2>
        </div>

        @if($pendingReleases->isEmpty())
            <x-empty-state icon="inbox" title="No pending releases" hint="All release submissions have been reviewed."/>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-page/50">
                        <tr class="border-b border-surface-border">
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Item</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Qty</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Unit</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Released To</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Office</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Submitted By</th>
                            @if(auth()->user()->isAdmin())
                                <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Dept</th>
                            @endif
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Date</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-border">
                        @foreach($pendingReleases as $tx)
                            <tr x-data="{ rejectOpen: false }">
                                <td class="px-6 py-3 font-medium text-ink-heading">{{ $tx->item_name_snapshot }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->qty }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->unit }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->receiver_name ?? '—' }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->released_to_office ?? '—' }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->releasedBy?->name ?? '—' }}</td>
                                @if(auth()->user()->isAdmin())
                                    <td class="px-6 py-3 text-ink-muted text-xs">{{ $tx->department?->name ?? '—' }}</td>
                                @endif
                                <td class="px-6 py-3 text-ink-body">{{ $tx->date_released }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-2 min-w-[120px]">
                                        {{-- Approve --}}
                                        <form method="POST" action="{{ route('approvals.approve', $tx) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-button type="submit" variant="primary" class="w-full">Approve</x-button>
                                        </form>

                                        {{-- Reject toggle --}}
                                        <x-button type="button" variant="ghost" class="w-full" @click="rejectOpen = !rejectOpen">
                                            Reject
                                        </x-button>

                                        {{-- Inline reject form --}}
                                        <div x-show="rejectOpen" x-cloak class="mt-1">
                                            <form method="POST" action="{{ route('approvals.reject', $tx) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-textarea name="notes" rows="2" required placeholder="Reason for rejection…" class="mb-2 w-full"/>
                                                <x-button type="submit" variant="danger" class="w-full">Confirm Reject</x-button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-bento-card>
</x-app-layout>
