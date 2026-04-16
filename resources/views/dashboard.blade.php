<x-app-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800">Dashboard</h1>
        <p class="text-sm text-gray-500 mt-1">MIS Office Inventory Overview</p>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Items in Stock</p>
            <p class="text-3xl font-semibold text-gray-800 mt-1">{{ $totalInStock }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total Released</p>
            <p class="text-3xl font-semibold text-amber-600 mt-1">{{ $totalReleased }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Pending Acknowledgment</p>
            <p class="text-3xl font-semibold text-red-600 mt-1">{{ $pendingAck }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Acknowledged</p>
            <p class="text-3xl font-semibold text-green-600 mt-1">{{ $acknowledged }}</p>
        </div>
    </div>

    {{-- Pending Acknowledgments Table --}}
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-sm font-semibold text-gray-700">Pending Acknowledgments</h2>
        </div>
        @if($pendingTransactions->isEmpty())
            <div class="px-6 py-10 text-center text-sm text-gray-400">No pending acknowledgments.</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Item</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Released To</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Office</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Qty</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Date</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingTransactions as $tx)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-800">{{ $tx->item_name_snapshot }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ $tx->receiver_name }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ $tx->released_to_office }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ $tx->qty }} {{ $tx->unit }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ $tx->date_released }}</td>
                        <td class="px-6 py-3">
                            <a href="{{ route('acknowledge.index') }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Acknowledge</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</x-app-layout>