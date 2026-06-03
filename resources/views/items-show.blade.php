<x-app-layout>
    <div class="mb-4">
        <a href="{{ route('items.index') }}" class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Back to Inventory
        </a>
    </div>

    <x-page-header :title="$item->name"
                   :subtitle="trim(($item->brand ?? '') . ' ' . ($item->model_number ? '— ' . $item->model_number : '')) ?: null">
        <x-slot:actions>
            @if($item->current_qty === 0)
                <x-status-badge status="pending">Out of stock</x-status-badge>
            @elseif($item->isBelowMinStock())
                <x-status-badge status="released">{{ $item->current_qty }} {{ $item->unit }} in stock</x-status-badge>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                    Low Stock
                </span>
            @else
                <x-status-badge status="acknowledged">{{ $item->current_qty }} {{ $item->unit }} in stock</x-status-badge>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-bento-card>
            <p class="text-xs text-ink-muted uppercase tracking-wide">Category</p>
            <p class="font-medium text-ink-heading mt-1">{{ $item->category ?? '—' }}</p>
        </x-bento-card>
        <x-bento-card>
            <p class="text-xs text-ink-muted uppercase tracking-wide">Serial No.</p>
            <p class="font-medium text-ink-heading mt-1">{{ $item->serial_number ?? '—' }}</p>
        </x-bento-card>
        <x-bento-card>
            <p class="text-xs text-ink-muted uppercase tracking-wide">Total Received</p>
            <p class="font-medium text-ink-heading mt-1">{{ $item->total_qty_received }} {{ $item->unit }}</p>
        </x-bento-card>
        <x-bento-card>
            <p class="text-xs text-ink-muted uppercase tracking-wide">Current Stock</p>
            <p class="font-medium text-ink-heading mt-1" x-data x-count-up>{{ $item->current_qty }}</p>
        </x-bento-card>
    </div>

    @if($item->expiry_date)
    <div class="mb-4">
        <x-bento-card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-ink-muted uppercase tracking-wide mb-1">Expiry Date</p>
                    <p class="text-lg font-semibold text-ink-heading">{{ $item->expiry_date->format('M d, Y') }}</p>
                    <p class="text-xs text-ink-muted mt-0.5">
                        @if($item->isExpired())
                            Expired {{ $item->expiry_date->diffForHumans() }}
                        @else
                            Expires {{ $item->expiry_date->diffForHumans() }}
                        @endif
                    </p>
                </div>
                @if($item->expiryStatus() === 'expired')
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-rose-100 text-rose-700">Expired</span>
                @elseif($item->expiryStatus() === 'soon')
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-amber-100 text-amber-700">Expiring soon</span>
                @else
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-emerald-100 text-emerald-700">Valid</span>
                @endif
            </div>
        </x-bento-card>
    </div>
    @endif

    <x-bento-card variant="hero" class="mb-4">
        <p class="text-xs uppercase tracking-wide opacity-80 font-medium">30-day movement</p>
        <div class="mt-3 h-20">
            <x-sparkline :data="$movement30" color="#ffffff"/>
        </div>
    </x-bento-card>

    <x-bento-card :padded="false">
        <div class="px-6 py-4 border-b border-surface-border">
            <h2 class="text-sm font-semibold text-ink-heading">Transaction History</h2>
        </div>
        @if($transactions->isEmpty())
            <x-empty-state icon="document-text" title="No transactions yet" hint="Receipts and releases will appear here."/>
        @else
            <x-table :headers="['Type','Qty','From / To','Office','Date','Status','']">
                @foreach($transactions as $tx)
                    <x-table.row>
                        <td class="px-6 py-3">
                            @if($tx->type === 'received')
                                <x-status-badge status="received">IN</x-status-badge>
                            @else
                                <x-status-badge status="released">OUT</x-status-badge>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->qty }} {{ $tx->unit }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->type === 'received' ? $tx->received_from : $tx->receiver_name }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->type === 'received' ? 'S&P Office' : $tx->released_to_office }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->type === 'received' ? $tx->date_received : $tx->date_released }}</td>
                        <td class="px-6 py-3">
                            @if($tx->type === 'received')
                                <x-status-badge status="received"/>
                            @elseif($tx->acknowledgment_status === 'acknowledged')
                                <x-status-badge status="acknowledged"/>
                            @else
                                <x-status-badge status="pending"/>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('transactions.show', $tx->id) }}" class="text-primary-600 hover:text-primary-700 text-xs font-medium">View →</a>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
        @endif
    </x-bento-card>

    {{-- Audit Log --}}
    <x-bento-card :padded="false" class="mt-4">
        <div class="px-6 py-4 border-b border-surface-border">
            <h2 class="text-sm font-semibold text-ink-heading">Audit Log</h2>
        </div>
        @if($logs->isEmpty())
            <x-empty-state icon="clipboard-document-list" title="No audit entries yet" hint="Quantity changes will appear here once inventory is updated."/>
        @else
            <div class="divide-y divide-surface-border">
                @foreach($logs as $log)
                    @php
                        $isPositive = $log->qty_change > 0;
                        $isZero     = $log->qty_change === 0;
                        $actionIcon = match($log->action) {
                            'approved_receive' => '✅',
                            'approved_release' => '📤',
                            'cancelled'        => '🚫',
                            'rejected'         => '❌',
                            default            => '📋',
                        };
                        $changeLabel = $isZero
                            ? '±0 ' . $item->unit
                            : ($isPositive ? '+' : '') . $log->qty_change . ' ' . $item->unit;
                    @endphp
                    <div class="px-6 py-3 flex items-center gap-4 text-sm">
                        <span class="text-base w-5 shrink-0">{{ $actionIcon }}</span>
                        <span class="font-medium text-ink-heading w-36 shrink-0">{{ $log->action }}</span>
                        <span class="{{ $isPositive ? 'text-emerald-600' : ($isZero ? 'text-ink-muted' : 'text-rose-600') }} font-medium w-20 shrink-0">
                            {{ $changeLabel }}
                        </span>
                        <span class="text-ink-muted w-32 shrink-0">
                            {{ $log->qty_before }} → {{ $log->qty_after }}
                        </span>
                        <span class="text-ink-muted shrink-0">
                            {{ $log->created_at?->format('M d, Y') ?? '—' }}
                        </span>
                        <span class="text-ink-muted shrink-0">
                            {{ $log->user?->name ?? '—' }}
                        </span>
                        @if($log->note)
                            <span class="text-ink-muted text-xs truncate">{{ $log->note }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-bento-card>
</x-app-layout>
