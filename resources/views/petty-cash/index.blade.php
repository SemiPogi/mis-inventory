<x-app-layout>
    <x-page-header title="Petty Cash Vouchers" subtitle="Track cash advances and purchases.">
        <x-slot name="actions">
            @if(auth()->user()->canCreateVoucher())
                <x-button href="{{ route('petty-cash.create') }}" variant="primary">New Voucher</x-button>
            @endif
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')" />
    @endif

    @if($vouchers->isEmpty())
        <x-empty-state icon="banknotes" title="No vouchers yet"
                       description="Submit a petty cash voucher to get started." />
    @else
        <x-bento-card>
            <x-table>
                <x-slot name="head">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">Voucher #</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">Store</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">OR #</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-ink-muted uppercase">Total</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-ink-muted uppercase">Change</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">By</th>
                    <th class="px-4 py-3"></th>
                </x-slot>
                @foreach($vouchers as $v)
                    <x-table.row>
                        <td class="px-4 py-3 font-mono text-sm text-primary-700">{{ $v->voucher_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ $v->date_purchased->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-sm">{{ $v->store_name }}</td>
                        <td class="px-4 py-3 text-sm text-ink-muted">{{ $v->or_number }}</td>
                        <td class="px-4 py-3 text-sm text-right font-medium">₱{{ number_format($v->total_amount, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right {{ $v->change_amount > 0 ? 'text-amber-600 font-semibold' : 'text-ink-muted' }}">
                            ₱{{ number_format($v->change_amount, 2) }}
                        </td>
                        <td class="px-4 py-3"><x-status-badge :status="$v->status" /></td>
                        <td class="px-4 py-3 text-sm text-ink-muted">{{ $v->creator->name }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('petty-cash.show', $v) }}"
                               class="text-xs text-primary-600 hover:underline">View</a>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
            <div class="px-4 py-3">{{ $vouchers->links() }}</div>
        </x-bento-card>
    @endif
</x-app-layout>
