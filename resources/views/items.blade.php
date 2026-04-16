<x-app-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800">Inventory</h1>
        <p class="text-sm text-gray-500 mt-1">All items received by the MIS Office</p>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
        <form method="GET" action="{{ route('items.index') }}" class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ request('search') }}"
                placeholder="Search item or brand..."
                class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 md:col-span-2">
            <select name="category" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                @endforeach
            </select>
            <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
                Filter
            </button>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200">
        @if($items->isEmpty())
            <div class="px-6 py-10 text-center text-sm text-gray-400">No items found.</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Item Name</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Category</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Brand</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Total Received</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Current Qty</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Unit</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-800">{{ $item->name }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ $item->category ?? '—' }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ $item->brand ?? '—' }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ $item->total_qty_received }}</td>
                        <td class="px-6 py-3">
                            @if($item->current_qty > 0)
                                <span class="bg-green-50 text-green-700 text-xs font-medium px-2 py-1 rounded-full">{{ $item->current_qty }}</span>
                            @else
                                <span class="bg-red-50 text-red-700 text-xs font-medium px-2 py-1 rounded-full">0</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-gray-600">{{ $item->unit }}</td>
                        <td class="px-6 py-3">
                            <a href="{{ route('items.show', $item->id) }}"
                                class="text-blue-600 hover:text-blue-800 text-xs font-medium">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $items->withQueryString()->links() }}
        </div>
        @endif
    </div>
</x-app-layout>