<x-app-layout>
    <x-page-header title="Receive Item" subtitle="Record items received from Supplies & Properties Office"/>

    <x-bento-card class="max-w-3xl" x-data x-init="$el.classList.add('animate-slide-up')">
        <form method="POST" action="{{ route('receive.store') }}">
            @csrf

            <p class="text-sm font-semibold text-ink-heading mb-4">Item Details</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <x-label for="name" required>Item Name</x-label>
                    <x-input id="name" name="name" :value="old('name')" required/>
                </div>
                <div>
                    <x-label for="category">Category</x-label>
                    <x-select id="category" name="category">
                        <option value="">Select category</option>
                        @foreach(['Office Supplies','Hardware','Peripherals','Consumables','Cables & Accessories','Networking','Furniture & Equipment','Other'] as $cat)
                            <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $cat }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <x-label for="brand">Brand</x-label>
                    <x-input id="brand" name="brand" :value="old('brand')"/>
                </div>
                <div>
                    <x-label for="model_number">Model Number</x-label>
                    <x-input id="model_number" name="model_number" :value="old('model_number')"/>
                </div>
                <div>
                    <x-label for="serial_number">Serial Number</x-label>
                    <x-input id="serial_number" name="serial_number" :value="old('serial_number')"/>
                </div>
                <div>
                    <x-label for="unit">Unit</x-label>
                    <x-input id="unit" name="unit" :value="old('unit', 'pcs')" placeholder="pcs / ream / box"/>
                </div>
                <div>
                    <x-label for="qty" required>Quantity</x-label>
                    <x-input id="qty" name="qty" type="number" min="1" :value="old('qty')" required/>
                </div>
            </div>

            <p class="text-sm font-semibold text-ink-heading mb-4">Source &amp; Reference</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <x-label for="received_from">Received From (S&amp;P Officer)</x-label>
                    <x-input id="received_from" name="received_from" :value="old('received_from')"/>
                </div>
                <div>
                    <x-label for="ris_iar_number">RIS / IAR Number</x-label>
                    <x-input id="ris_iar_number" name="ris_iar_number" :value="old('ris_iar_number')"/>
                </div>
                <div>
                    <x-label for="date_received" required>Date Received</x-label>
                    <x-input id="date_received" name="date_received" type="date" :value="old('date_received', date('Y-m-d'))" required/>
                </div>
                <div>
                    <x-label for="received_by">Received By</x-label>
                    <x-input id="received_by" name="received_by" :value="old('received_by', auth()->user()->name)"/>
                </div>
                <div class="md:col-span-2">
                    <x-label for="remarks">Remarks</x-label>
                    <x-textarea id="remarks" name="remarks">{{ old('remarks') }}</x-textarea>
                </div>
            </div>

            <div class="flex gap-3">
                <x-button type="submit" variant="primary">
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4"/>
                    Record Receipt
                </x-button>
                <x-button as="a" variant="ghost" href="{{ route('dashboard') }}">Cancel</x-button>
            </div>
        </form>
    </x-bento-card>
</x-app-layout>
