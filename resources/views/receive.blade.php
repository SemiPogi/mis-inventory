<x-app-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800">Receive Item</h1>
        <p class="text-sm text-gray-500 mt-1">Record items received from Supplies & Properties Office</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6 max-w-3xl">
        <form method="POST" action="{{ route('receive.store') }}">
            @csrf

            <p class="text-sm font-medium text-gray-700 mb-4">Item Details</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Item Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Category</label>
                    <select name="category" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select category</option>
                        <option value="Office Supplies" {{ old('category') == 'Office Supplies' ? 'selected' : '' }}>Office Supplies</option>
                        <option value="Hardware" {{ old('category') == 'Hardware' ? 'selected' : '' }}>Hardware</option>
                        <option value="Peripherals" {{ old('category') == 'Peripherals' ? 'selected' : '' }}>Peripherals</option>
                        <option value="Consumables" {{ old('category') == 'Consumables' ? 'selected' : '' }}>Consumables</option>
                        <option value="Cables & Accessories" {{ old('category') == 'Cables & Accessories' ? 'selected' : '' }}>Cables & Accessories</option>
                        <option value="Networking" {{ old('category') == 'Networking' ? 'selected' : '' }}>Networking</option>
                        <option value="Furniture & Equipment" {{ old('category') == 'Furniture & Equipment' ? 'selected' : '' }}>Furniture & Equipment</option>
                        <option value="Other" {{ old('category') == 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Brand</label>
                    <input type="text" name="brand" value="{{ old('brand') }}"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Model Number</label>
                    <input type="text" name="model_number" value="{{ old('model_number') }}"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Serial Number</label>
                    <input type="text" name="serial_number" value="{{ old('serial_number') }}"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Unit</label>
                    <input type="text" name="unit" value="{{ old('unit', 'pcs') }}"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="pcs / ream / box">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Quantity *</label>
                    <input type="number" name="qty" value="{{ old('qty') }}" required min="1"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <p class="text-sm font-medium text-gray-700 mb-4">Source & Reference</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Received From (S&P Officer)</label>
                    <input type="text" name="received_from" value="{{ old('received_from') }}"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">RIS / IAR Number</label>
                    <input type="text" name="ris_iar_number" value="{{ old('ris_iar_number') }}"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Date Received *</label>
                    <input type="date" name="date_received" value="{{ old('date_received', date('Y-m-d')) }}" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Received By</label>
                    <input type="text" name="received_by" value="{{ old('received_by', auth()->user()->name) }}"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                    Record Receipt
                </button>
                <a href="{{ route('dashboard') }}"
                    class="border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium px-6 py-2 rounded-lg">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</x-app-layout>