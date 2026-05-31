<x-app-layout>
    <x-page-header title="Transactions" subtitle="Full log of all received and released items"/>

    {{-- Filter bar --}}
    <x-bento-card class="mb-4">
        <form method="GET" action="{{ route('transactions.index') }}"
              class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
            <div class="md:col-span-2">
                <x-label for="search">Search</x-label>
                <x-input id="search" name="search" :value="request('search')" placeholder="Item, office, person…"/>
            </div>
            <div>
                <x-label for="type">Type</x-label>
                <x-select id="type" name="type">
                    <option value="">All</option>
                    <option value="received" @selected(request('type') === 'received')>Received</option>
                    <option value="released" @selected(request('type') === 'released')>Released</option>
                </x-select>
            </div>
            <div>
                <x-label for="status">Status</x-label>
                <x-select id="status" name="status">
                    <option value="">All</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="acknowledged" @selected(request('status') === 'acknowledged')>Acknowledged</option>
                </x-select>
            </div>
            <div>
                <x-label for="date_from">From</x-label>
                <x-input id="date_from" name="date_from" type="date" :value="request('date_from')"/>
            </div>
            <div>
                <x-label for="date_to">To</x-label>
                <x-input id="date_to" name="date_to" type="date" :value="request('date_to')"/>
            </div>
            <div class="md:col-span-6 flex justify-end gap-2">
                @if(request()->hasAny(['search','type','status','date_from','date_to']))
                    <x-button as="a" variant="ghost" href="{{ route('transactions.index') }}">Clear</x-button>
                @endif
                <x-button type="submit" variant="primary">
                    <x-heroicon-o-funnel class="w-4 h-4"/>
                    Apply filters
                </x-button>
            </div>
        </form>
    </x-bento-card>

    <x-bento-card :padded="false">
        @if($transactions->isEmpty())
            <x-empty-state icon="inbox" title="No transactions found" hint="Adjust filters or record a new transaction."/>
        @else
            <x-table :headers="['Type','Item','Qty','From / To','Office','Date','Status','']">
                @foreach($transactions as $tx)
                    <x-table.row>
                        <td class="px-6 py-3">
                            @if($tx->type === 'received')
                                <x-status-badge status="received">IN</x-status-badge>
                            @else
                                <x-status-badge status="released">OUT</x-status-badge>
                            @endif
                        </td>
                        <td class="px-6 py-3 font-medium text-ink-heading">{{ $tx->item_name_snapshot }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->qty }} {{ $tx->unit }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->type === 'received' ? $tx->received_from : $tx->receiver_name }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->type === 'received' ? 'S&P Office' : $tx->released_to_office }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->type === 'received' ? $tx->date_received : $tx->date_released }}</td>
                        <td class="px-6 py-3">
                            @if($tx->head_approval_status === 'pending')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">
                                    Pending Approval
                                </span>
                            @elseif($tx->head_approval_status === 'rejected')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700"
                                      title="{{ $tx->head_rejection_notes }}">
                                    Rejected
                                </span>
                            @elseif($tx->type === 'received')
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
            <div class="px-6 py-4 border-t border-surface-border">
                {{ $transactions->withQueryString()->links() }}
            </div>
        @endif
    </x-bento-card>
</x-app-layout>
