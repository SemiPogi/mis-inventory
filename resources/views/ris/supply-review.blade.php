<x-app-layout>
    <x-page-header :title="'Process: ' . $ris->ris_number"
                   :subtitle="'Issuing to ' . $ris->requestingDept->name">
        <x-slot name="actions">
            <x-button href="{{ route('ris.supply.index') }}" variant="ghost">← Back to Queue</x-button>
        </x-slot>
    </x-page-header>

    @if(session('error'))
        <x-toast type="error" :message="session('error')"/>
    @endif

    <div class="grid grid-cols-3 gap-6">
        {{-- Issue Form --}}
        <div class="col-span-2 space-y-4">
            <x-bento-card>
                <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-1">Request Details</p>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm mt-3">
                    <div>
                        <dt class="text-ink-muted text-xs">Department</dt>
                        <dd class="font-medium text-ink-heading">{{ $ris->requestingDept->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted text-xs">Requested By</dt>
                        <dd class="text-ink-body">{{ $ris->requestedBy->name }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-ink-muted text-xs">Purpose</dt>
                        <dd class="text-ink-body">{{ $ris->purpose }}</dd>
                    </div>
                    @if($ris->notes)
                        <div class="col-span-2">
                            <dt class="text-ink-muted text-xs">Notes</dt>
                            <dd class="text-ink-body">{{ $ris->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </x-bento-card>

            <form method="POST" action="{{ route('ris.supply.issue', $ris) }}">
                @csrf @method('PATCH')

                <x-bento-card :padded="false">
                    <div class="px-6 py-4 border-b border-surface-border flex items-center justify-between">
                        <p class="text-sm font-medium text-ink-heading">Set Issued Quantities</p>
                        <p class="text-xs text-ink-muted">Enter 0 if an item cannot be fulfilled.</p>
                    </div>

                    <div class="divide-y divide-surface-border">
                        @foreach($ris->items as $item)
                            <div class="px-6 py-4 grid grid-cols-12 gap-4 items-center">
                                {{-- Item info --}}
                                <div class="col-span-5">
                                    <p class="text-sm font-medium text-ink-heading">{{ $item->item_name }}</p>
                                    @if($item->stock_no)
                                        <p class="text-xs text-ink-muted font-mono">{{ $item->stock_no }}</p>
                                    @endif
                                </div>

                                {{-- Unit --}}
                                <div class="col-span-2 text-center">
                                    <p class="text-xs text-ink-muted mb-0.5">Unit</p>
                                    <p class="text-sm text-ink-body">{{ $item->unit }}</p>
                                </div>

                                {{-- Requested Qty --}}
                                <div class="col-span-2 text-center">
                                    <p class="text-xs text-ink-muted mb-0.5">Requested</p>
                                    <p class="text-sm font-medium text-ink-body">{{ $item->requested_qty }}</p>
                                </div>

                                {{-- Issued Qty input --}}
                                <div class="col-span-3">
                                    <label class="text-xs text-ink-muted mb-1 block">Issued Qty *</label>
                                    <input
                                        type="number"
                                        name="issued_qty[{{ $item->id }}]"
                                        value="{{ old("issued_qty.{$item->id}", $item->requested_qty) }}"
                                        min="0"
                                        max="{{ $item->requested_qty }}"
                                        required
                                        class="w-full rounded border border-surface-border bg-surface-tile text-ink-body px-2 py-1.5 text-sm text-center focus:outline-none focus:ring-1 focus:ring-primary-500 @error("issued_qty.{$item->id}") border-danger @enderror"
                                    />
                                    @error("issued_qty.{$item->id}")
                                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-bento-card>

                <div class="flex justify-end gap-3 mt-4">
                    <x-button href="{{ route('ris.supply.index') }}" variant="ghost">Cancel</x-button>
                    <x-button type="submit" variant="primary">
                        <x-heroicon-o-paper-airplane class="w-4 h-4"/>
                        Issue Items
                    </x-button>
                </div>
            </form>
        </div>

        {{-- Sidebar: Supply Stock Reference --}}
        <div class="space-y-4">
            <x-bento-card>
                <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-3">Supply Stock Reference</p>
                @if($supplyItems->isEmpty())
                    <p class="text-xs text-ink-muted italic">No supply inventory found.</p>
                @else
                    <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                        @foreach($supplyItems as $stock)
                            <div class="flex items-center justify-between text-xs py-1.5 border-b border-surface-border last:border-0">
                                <span class="text-ink-body font-medium truncate pr-2">{{ $stock->name }}</span>
                                <span class="shrink-0 font-mono {{ $stock->current_qty <= 0 ? 'text-rose-600 font-semibold' : 'text-ink-muted' }}">
                                    {{ $stock->current_qty }} {{ $stock->unit }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-bento-card>

            <x-bento-card>
                <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-3">Approval Info</p>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-ink-muted text-xs">Head Approved By</dt>
                        <dd class="text-ink-body">{{ $ris->headApprovedBy?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted text-xs">Approved At</dt>
                        <dd class="text-ink-body">{{ $ris->head_approved_at?->format('M d, Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted text-xs">Date Submitted</dt>
                        <dd class="text-ink-body">{{ $ris->created_at->format('M d, Y') }}</dd>
                    </div>
                </dl>
            </x-bento-card>
        </div>
    </div>
</x-app-layout>
