<x-app-layout>
    <div class="mb-6">
        <a href="{{ route('items.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Inventory</a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6 max-w-3xl mb-6">
        <div class="flex items-start justify-between mb-6">
            <div>
                <h1 class="text-xl font-semibold text-gray-800">{{ $item->name }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $item->brand ?? '' }} {{ $item->model_number ? '— '.$item->model_number : '' }}</p>
            </div>
            @if($item->current_qty > 0)
                <span class="bg-green-50 text-green-700 text-sm font-medium px-3 py-1 rounded-full">{{ $item->current_qty }} {{ $item->unit }} in stock</span>
            @else
                <span class="bg-red-50 text-red-700 text-sm font-medium px-3 py-1 rounded-full">Out of stock</span>
            @endif
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-xs text-gray-400 mb-1">Category</p>
                <p class="font-medium text-gray-800">{{ $item->category ?? '—' }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-xs text-gray-400 mb-1">Serial No.</p>
                <p class="font-medium text-gray-800">{{ $item->serial_number ?? '—' }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-xs text-gray-400 mb-1">Total Received</p>
                <p class="font-medium text-gray-800">{{ $item->total_qty_received }} {{ $item->unit }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-xs text-gray-400 mb-1">Current Stock</p>
                <p class="font-medium text-gray-800">{{ $item->current_qty }} {{ $item->unit }}</p>
            </div>
        </div>
    </div>

    {{-- Transaction History --}}
    <div class="bg-white rounded-xl border border-gray-200 max-w-3xl">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-sm font-semibold text-gray-700">Transaction History</h2>
        </div>
        @if($transactions->isEmpty())
            <div class="px-6 py-10 text-center text-sm text-gray-400">No transactions for this item.</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Type</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Qty</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">From / To</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Office</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Date</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $tx)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="px-6 py-3">
                            @if($tx->type == 'received')
                                <span class="bg-blue-50 text-blue-700 text-xs font-medium px-2 py-1 rounded-full">IN</span>
                            @else
                                <span class="bg-amber-50 text-amber-700 text-xs font-medium px-2 py-1 rounded-full">OUT</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-gray-600">{{ $tx->qty }} {{ $tx->unit }}</td>
                        <td class="px-6 py-3 text-gray-600">
                            {{ $tx->type == 'received' ? $tx->received_from : $tx->receiver_name }}
                        </td>
                        <td class="px-6 py-3 text-gray-600">
                            {{ $tx->type == 'received' ? 'S&P Office' : $tx->released_to_office }}
                        </td>
                        <td class="px-6 py-3 text-gray-600">
                            {{ $tx->type == 'received' ? $tx->date_received : $tx->date_released }}
                        </td>
                        <td class="px-6 py-3">
                            @if($tx->type == 'received')
                                <span class="bg-blue-50 text-blue-700 text-xs font-medium px-2 py-1 rounded-full">Received</span>
                            @elseif($tx->acknowledgment_status == 'acknowledged')
                                <span class="bg-green-50 text-green-700 text-xs font-medium px-2 py-1 rounded-full">Acknowledged</span>
                            @else
                                <span class="bg-red-50 text-red-700 text-xs font-medium px-2 py-1 rounded-full">Pending</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            <a href="{{ route('transactions.show', $tx->id) }}"
                                class="text-blue-600 hover:text-blue-800 text-xs font-medium">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</x-app-layout>