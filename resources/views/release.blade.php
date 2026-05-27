<x-app-layout>
    <x-page-header title="Release Item" subtitle="Release items to other offices"/>

    <x-bento-card class="max-w-3xl" x-data="releaseForm()" x-init="$el.classList.add('animate-slide-up')">
        <form method="POST" action="{{ route('release.store') }}" @submit.prevent="onSubmit">
            @csrf

            <p class="text-sm font-semibold text-ink-heading mb-4">Select Item</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="md:col-span-2">
                    <x-label for="item_id" required>Item</x-label>
                    <x-select id="item_id" name="item_id" required x-model="itemId" @change="onItemChange($event)">
                        <option value="">— Select item in stock —</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}"
                                    data-qty="{{ $item->current_qty }}"
                                    data-unit="{{ $item->unit }}"
                                    @selected(old('item_id') == $item->id)>
                                {{ $item->name }}{{ $item->brand ? ' — '.$item->brand : '' }}
                                ({{ $item->current_qty }} {{ $item->unit }} available)
                            </option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <x-label>Available Qty</x-label>
                    <x-input readonly x-model="availableLabel" class="bg-surface-page text-ink-muted"/>
                </div>
                <div>
                    <x-label for="qty" required>Quantity to Release</x-label>
                    <x-input id="qty" name="qty" type="number" min="1" :value="old('qty')" required x-model.number="qty"/>
                </div>
            </div>

            <p class="text-sm font-semibold text-ink-heading mb-4">Release To</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <x-label for="released_to_office" required>Receiving Office</x-label>
                    <x-input id="released_to_office" name="released_to_office" :value="old('released_to_office')" required placeholder="e.g. Nursing Unit 3"/>
                </div>
                <div>
                    <x-label for="receiver_name" required>Receiver Name</x-label>
                    <x-input id="receiver_name" name="receiver_name" :value="old('receiver_name')" required/>
                </div>
                <div>
                    <x-label for="receiver_designation">Receiver Designation</x-label>
                    <x-input id="receiver_designation" name="receiver_designation" :value="old('receiver_designation')" placeholder="e.g. Head Nurse"/>
                </div>
                <div>
                    <x-label for="date_released" required>Date Released</x-label>
                    <x-input id="date_released" name="date_released" type="date" :value="old('date_released', date('Y-m-d'))" required/>
                </div>
                <div>
                    <x-label for="released_by">Released By</x-label>
                    <x-input id="released_by" name="released_by" :value="old('released_by', auth()->user()->name)"/>
                </div>
                <div>
                    <x-label for="purpose">Purpose</x-label>
                    <x-input id="purpose" name="purpose" :value="old('purpose')" placeholder="e.g. For printer replacement"/>
                </div>
                <div class="md:col-span-2">
                    <x-label for="remarks">Remarks</x-label>
                    <x-textarea id="remarks" name="remarks">{{ old('remarks') }}</x-textarea>
                </div>
            </div>

            <div class="flex gap-3">
                <x-button type="submit" variant="primary">
                    <x-heroicon-o-arrow-up-tray class="w-4 h-4"/>
                    Release Item
                </x-button>
                <x-button as="a" variant="ghost" href="{{ route('dashboard') }}">Cancel</x-button>
            </div>

            {{-- Confirm modal --}}
            <div x-show="confirming"
                 x-cloak
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                <div class="bg-surface-tile rounded-2xl shadow-tile-hover p-6 max-w-sm mx-4 animate-pop">
                    <h3 class="text-base font-semibold text-ink-heading mb-2">Large release</h3>
                    <p class="text-sm text-ink-body mb-5">
                        You're releasing <strong x-text="qty"></strong> out of <strong x-text="available"></strong> available
                        (<span x-text="Math.round((qty / available) * 100)"></span>%). Continue?
                    </p>
                    <div class="flex justify-end gap-2">
                        <x-button type="button" variant="ghost" @click="confirming = false">Cancel</x-button>
                        <x-button type="button" variant="primary" @click="confirm()">Yes, release</x-button>
                    </div>
                </div>
            </div>
        </form>
    </x-bento-card>

    <script>
        function releaseForm() {
            return {
                itemId: '{{ old('item_id', '') }}',
                qty: {{ (int) old('qty', 0) }},
                available: 0,
                unit: '',
                confirming: false,
                get availableLabel() {
                    return this.available ? `${this.available} ${this.unit}` : '';
                },
                init() {
                    if (this.itemId) this.refreshAvailable();
                },
                onItemChange(e) {
                    this.refreshAvailable(e.target);
                },
                refreshAvailable(selectEl) {
                    selectEl = selectEl || document.getElementById('item_id');
                    const opt = selectEl.options[selectEl.selectedIndex];
                    this.available = parseInt(opt?.dataset.qty || 0, 10);
                    this.unit = opt?.dataset.unit || '';
                },
                onSubmit(e) {
                    if (this.available > 0 && this.qty > this.available * 0.5 && !this.confirming) {
                        this.confirming = true;
                        return;
                    }
                    e.target.submit();
                },
                confirm() {
                    this.confirming = false;
                    document.querySelector('form').submit();
                },
            }
        }
    </script>
</x-app-layout>
