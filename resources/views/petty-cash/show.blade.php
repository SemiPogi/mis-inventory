<x-app-layout>
    <x-page-header :title="$pettyCash->voucher_number" subtitle="Petty Cash Voucher">
        <x-slot name="actions">
            <x-button href="{{ route('petty-cash.print', $pettyCash) }}" variant="secondary" target="_blank">Print</x-button>
            <x-button href="{{ route('petty-cash.index') }}" variant="secondary">Back</x-button>
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')" />
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Voucher info --}}
        <x-bento-card class="lg:col-span-2 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-ink-heading uppercase tracking-wide">Voucher Info</h2>
                <x-status-badge :status="$pettyCash->status" />
            </div>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <dt class="text-ink-muted">Date Purchased</dt>
                <dd>{{ $pettyCash->date_purchased->format('F d, Y') }}</dd>
                <dt class="text-ink-muted">OR Number</dt>
                <dd class="font-mono">{{ $pettyCash->or_number }}</dd>
                <dt class="text-ink-muted">Store / Supplier</dt>
                <dd>{{ $pettyCash->store_name }}</dd>
                <dt class="text-ink-muted">Releasing Officer</dt>
                <dd>{{ $pettyCash->releasing_officer }}</dd>
                <dt class="text-ink-muted">Prepared By</dt>
                <dd>{{ $pettyCash->creator->name }}</dd>
                @if($pettyCash->remarks)
                    <dt class="text-ink-muted">Remarks</dt>
                    <dd>{{ $pettyCash->remarks }}</dd>
                @endif
            </dl>

            {{-- Line items table --}}
            <div class="mt-2">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-border text-left text-xs text-ink-muted uppercase">
                            <th class="pb-2">Item</th>
                            <th class="pb-2 text-right">Qty</th>
                            <th class="pb-2">Unit</th>
                            <th class="pb-2 text-right">Unit Cost</th>
                            <th class="pb-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-border">
                        @foreach($pettyCash->items as $line)
                            <tr>
                                <td class="py-2">{{ $line->item_name }}</td>
                                <td class="py-2 text-right">{{ $line->qty }}</td>
                                <td class="py-2 text-ink-muted">{{ $line->unit }}</td>
                                <td class="py-2 text-right">₱{{ number_format($line->unit_cost, 2) }}</td>
                                <td class="py-2 text-right font-medium">₱{{ number_format($line->total_cost, 2) }}</td>
                            </tr>
                        @endforeach
                        @if($pettyCash->transport_fee > 0)
                            <tr class="text-ink-muted italic">
                                <td colspan="4" class="py-2">Transport Fee</td>
                                <td class="py-2 text-right">₱{{ number_format($pettyCash->transport_fee, 2) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </x-bento-card>

        {{-- Summary + Actions --}}
        <div class="space-y-4">
            <x-bento-card class="space-y-3 text-sm">
                <h2 class="text-sm font-semibold text-ink-heading uppercase tracking-wide">Summary</h2>
                <div class="space-y-1">
                    <div class="flex justify-between text-ink-muted">
                        <span>Amount Requested</span>
                        <span>₱{{ number_format($pettyCash->requested_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-ink-muted">
                        <span>Total Spent</span>
                        <span>₱{{ number_format($pettyCash->total_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-base border-t border-surface-border pt-2
                                {{ $pettyCash->change_amount > 0 ? 'text-amber-700' : 'text-ink-heading' }}">
                        <span>Change Due</span>
                        <span>₱{{ number_format($pettyCash->change_amount, 2) }}</span>
                    </div>
                </div>
            </x-bento-card>

            {{-- Acknowledge --}}
            @if($pettyCash->status === 'submitted' && auth()->user()->canCreateVoucher())
                <x-bento-card class="space-y-2">
                    <p class="text-sm text-ink-muted">Confirm that the purchase details and change amount are correct.</p>
                    <form method="POST" action="{{ route('petty-cash.acknowledge', $pettyCash) }}">
                        @csrf @method('PATCH')
                        <x-button type="submit" variant="primary" class="w-full">Acknowledge Voucher</x-button>
                    </form>
                </x-bento-card>
            @endif

            {{-- Settle --}}
            @if($pettyCash->status === 'acknowledged' && auth()->user()->canSettleVoucher())
                <x-bento-card class="space-y-2 border-l-4 border-amber-400">
                    <p class="text-sm font-medium text-amber-700">
                        Change of ₱{{ number_format($pettyCash->change_amount, 2) }} must be returned.
                    </p>
                    <p class="text-xs text-ink-muted">Click below once the change has been physically received.</p>
                    <form method="POST" action="{{ route('petty-cash.settle', $pettyCash) }}">
                        @csrf @method('PATCH')
                        <x-button type="submit" variant="primary" class="w-full">Mark Change Returned</x-button>
                    </form>
                </x-bento-card>
            @endif

            {{-- Settled info --}}
            @if($pettyCash->status === 'settled')
                <x-bento-card class="space-y-1 text-sm border-l-4 border-primary-500">
                    <p class="font-medium text-primary-700">Fully Settled</p>
                    <p class="text-ink-muted">Change returned by {{ $pettyCash->changeReturnedBy?->name }}</p>
                    <p class="text-ink-muted">{{ $pettyCash->change_returned_at?->format('M d, Y g:i A') }}</p>
                </x-bento-card>
            @endif

            {{-- Admin delete --}}
            @if(auth()->user()->isAdmin())
                <form method="POST" action="{{ route('petty-cash.destroy', $pettyCash) }}"
                      onsubmit="return confirm('Delete this voucher permanently?')">
                    @csrf @method('DELETE')
                    <x-button type="submit" variant="danger" class="w-full">Delete Voucher</x-button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
