<x-app-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800">Release Item</h1>
        <p class="text-sm text-gray-500 mt-1">Release items to other offices</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6 max-w-3xl">
        <form method="POST" action="{{ route('release.store') }}">
            @csrf

            <p class="text-sm font-medium text-gray-700 mb-4">Select Item</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Item *</label>
                    <select name="item_id" required id="item-select"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        onchange="updateAvailable(this)">
                        <option value="">— Select item in stock —</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}"
                                data-qty="{{ $item->current_qty }}"
                                data-unit="{{ $item->unit }}"
                                {{ old('item_id') == $item->id ? 'selected' : '' }}>
                                {{ $item->name }}{{ $item->brand ? ' — '.$item->brand : '' }} ({{ $item->current_qty }} {{ $item->unit }} available)
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Available Qty</label>
                    <input type="text" id="available-qty" readonly
                        class="w-full border border-gray-100 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Quantity to Release *</label>
                    <input type="number" name="qty" value="{{ old('qty') }}" required min="1"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <p class="text-sm font-medium text-gray-700 mb-4">Release To</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Receiving Office *</label>
                    <input type="text" name="released_to_office" value="{{ old('released_to_office') }}" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g. Nursing Unit 3">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Receiver Name *</label>
                    <input type="text" name="receiver_name" value="{{ old('receiver_name') }}" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Receiver Designation</label>
                    <input type="text" name="receiver_designation" value="{{ old('receiver_designation') }}"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g. Head Nurse">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Date Released *</label>
                    <input type="date" name="date_released" value="{{ old('date_released', date('Y-m-d')) }}" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Released By</label>
                    <input type="text" name="released_by" value="{{ old('released_by', auth()->user()->name) }}"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Purpose</label>
                    <input type="text" name="purpose" value="{{ old('purpose') }}"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g. For printer replacement">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Remarks</label>
                    <textarea name="remarks" rows="3"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('remarks') }}</textarea>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-6 py-2 rounded-lg">
                    Release Item
                </button>
                <a href="{{ route('dashboard') }}"
                    class="border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium px-6 py-2 rounded-lg">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        function updateAvailable(select) {
            const option = select.options[select.selectedIndex];
            const qty = option.dataset.qty;
            const unit = option.dataset.unit;
            document.getElementById('available-qty').value = qty ? qty + ' ' + unit : '';
        }
    </script>
</x-app-layout>