<x-app-layout>
    <x-page-header title="Approvals" subtitle="Pending receive and release requests awaiting your approval"/>

    @if(session('warning'))
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0 text-amber-500"/>
            {{ session('warning') }}
        </div>
    @endif

    {{-- Action guide --}}
    @if($pendingReceives->isNotEmpty() || $pendingReleases->isNotEmpty())
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-800">
            <x-heroicon-o-shield-check class="w-5 h-5 mt-0.5 shrink-0 text-violet-500"/>
            <div>
                <p class="font-medium">Review carefully before approving</p>
                <p class="text-violet-700 mt-0.5">
                    <strong>Approving a Receive</strong> adds items to inventory immediately. &nbsp;
                    <strong>Approving a Release</strong> deducts stock and starts the acknowledgment flow. &nbsp;
                    Rejected submissions leave inventory unchanged.
                </p>
            </div>
        </div>
    @endif

    <div x-data="approvalManager()" :class="selected.length > 0 ? 'pb-24' : ''">

    {{-- Bulk-approve form (hidden; inputs injected by Alpine) --}}
    <form x-ref="bulkForm" method="POST" action="{{ route('approvals.bulk-approve') }}" class="hidden">
        @csrf
        <template x-for="id in selected" :key="id">
            <input type="hidden" name="ids[]" :value="id">
        </template>
    </form>

    {{-- ── Pending Receives ─────────────────────────────────────── --}}
    @php $receiveIds = $pendingReceives->pluck('id')->all(); @endphp
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
                            <th class="px-4 py-3 w-10">
                                <input type="checkbox"
                                       class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                       :checked="allSelected({{ json_encode($receiveIds) }})"
                                       x-effect="$el.indeterminate = {{ json_encode($receiveIds) }}.some(id => selected.includes(id)) && !{{ json_encode($receiveIds) }}.every(id => selected.includes(id))"
                                       @change="$event.target.checked ? selectAll({{ json_encode($receiveIds) }}) : deselectAll({{ json_encode($receiveIds) }})">
                            </th>
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
                                <td class="px-4 py-3 w-10">
                                    <input type="checkbox"
                                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                           :checked="isSelected({{ $tx->id }})"
                                           @change="toggle({{ $tx->id }})">
                                </td>
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
    @php $releaseIds = $pendingReleases->pluck('id')->all(); @endphp
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
                            <th class="px-4 py-3 w-10">
                                <input type="checkbox"
                                       class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                       :checked="allSelected({{ json_encode($releaseIds) }})"
                                       x-effect="$el.indeterminate = {{ json_encode($releaseIds) }}.some(id => selected.includes(id)) && !{{ json_encode($releaseIds) }}.every(id => selected.includes(id))"
                                       @change="$event.target.checked ? selectAll({{ json_encode($releaseIds) }}) : deselectAll({{ json_encode($releaseIds) }})">
                            </th>
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
                                <td class="px-4 py-3 w-10">
                                    <input type="checkbox"
                                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                           :checked="isSelected({{ $tx->id }})"
                                           @change="toggle({{ $tx->id }})">
                                </td>
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

    {{-- Sticky action bar — appears when ≥1 checkbox is checked --}}
    <div x-show="selected.length > 0"
         x-cloak
         class="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-surface-border shadow-lg px-6 py-4 flex items-center gap-4">
        <span class="text-sm text-ink-body">
            <span x-text="selected.length"></span> selected
        </span>
        <x-button variant="primary" @click="$refs.bulkForm.submit()">
            Approve Selected (<span x-text="selected.length"></span>)
        </x-button>
    </div>

    </div>{{-- end x-data="approvalManager()" --}}

    <script>
        function approvalManager() {
            return {
                selected: [],
                toggle(id) {
                    const idx = this.selected.indexOf(id);
                    idx === -1 ? this.selected.push(id) : this.selected.splice(idx, 1);
                },
                selectAll(ids) {
                    ids.forEach(id => {
                        if (!this.selected.includes(id)) this.selected.push(id);
                    });
                },
                deselectAll(ids) {
                    this.selected = this.selected.filter(id => !ids.includes(id));
                },
                isSelected(id) {
                    return this.selected.includes(id);
                },
                allSelected(ids) {
                    return ids.length > 0 && ids.every(id => this.selected.includes(id));
                },
            }
        }
    </script>

    {{-- ── RIS Prompt Modal ─────────────────────────────────────── --}}
    @if(session('suggest_ris'))
        @php $risData = session('suggest_ris') @endphp
        <div x-data="{ open: true }"
             x-show="open"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-surface-tile rounded-2xl shadow-tile-hover p-6 max-w-md mx-4 animate-pop">
                <h3 class="text-base font-semibold text-ink-heading mb-3">Create a RIS for this transaction?</h3>

                @if(!($risData['bulk'] ?? false))
                    <p class="text-sm text-ink-body mb-1">
                        You just approved:
                        <strong>{{ $risData['qty'] }} {{ $risData['unit'] }} of {{ $risData['item'] }}</strong>
                    </p>
                    <p class="text-sm text-ink-muted mb-4">
                        ({{ ucfirst($risData['type']) }} — {{ $risData['dept'] ?? '—' }})
                    </p>
                    <p class="text-sm text-ink-body mb-5">
                        Would you like to open the RIS form pre-filled with this transaction's details?
                    </p>
                @else
                    <p class="text-sm text-ink-body mb-5">
                        You approved <strong>{{ $risData['count'] }}</strong>
                        {{ $risData['count'] === 1 ? 'transaction' : 'transactions' }}.
                        Would you like to create a RIS?
                    </p>
                @endif

                <div class="flex justify-end gap-3">
                    <x-button type="button" variant="ghost" @click="open = false">Not Now</x-button>

                    @if(!($risData['bulk'] ?? false))
                        <x-button as="a" variant="primary"
                            href="{{ route('ris.create', array_filter([
                                'purpose'       => ucfirst($risData['type']).' '.$risData['qty'].' '.$risData['unit'].' of '.$risData['item'],
                                'department_id' => $risData['dept_id'],
                            ])) }}">
                            Yes, Create RIS →
                        </x-button>
                    @else
                        <x-button as="a" variant="primary" href="{{ route('ris.create') }}">
                            Yes, Create RIS →
                        </x-button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
