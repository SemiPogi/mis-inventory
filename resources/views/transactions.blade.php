<x-app-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800">Transactions</h1>
        <p class="text-sm text-gray-500 mt-1">Full log of all received and released items</p>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
        <form method="GET" action="{{ route('transactions.index') }}" class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <input type="text" name="search" value="{{ request('search') }}"
                placeholder="Search item, office, person..."
                class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 md:col-span-2">
            <select name="type" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All types</option>
                <option value="received" {{ request('type') == 'received' ? 'selected' : '' }}>Received</option>
                <option value="released" {{ request('type') == 'released' ? 'selected' : '' }}>Released</option>
            </select>
            <select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All status</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="acknowledged" {{ request('status') == 'acknowledged' ? 'selected' : '' }}>Acknowledged</option>
            </select>
            <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
                Filter
            </button>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200">
        @if($transactions->isEmpty())
            <div class="px-6 py-10 text-center text-sm text-gray-400">No transactions found.</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Type</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Item</th>
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
                        <td class="px-6 py-3 font-medium text-gray-800">{{ $tx->item_name_snapshot }}</td>
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
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $transactions->withQueryString()->links() }}
        </div>
        @endif
    </div>
</x-app-layout>