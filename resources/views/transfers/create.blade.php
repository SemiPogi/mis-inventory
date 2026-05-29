<x-app-layout>
    <x-page-header title="New Transfer" subtitle="Move items from your department to another.">
        <x-slot name="actions">
            <x-button href="{{ route('transfers.index') }}" variant="ghost">← Back</x-button>
        </x-slot>
    </x-page-header>

    @if($errors->any())
        <x-bento-card>
            <ul class="text-sm text-danger space-y-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </x-bento-card>
    @endif

    <div x-data="{
        selectedItems: [],
        addItem(id, name, unit, maxQty) {
            if (this.selectedItems.find(i => i.id == id)) return;
            this.selectedItems.push({ id, name, unit, maxQty, qty: 1 });
        },
        removeItem(id) { this.selectedItems = this.selectedItems.filter(i => i.id != id); },
        search: '',
        get filtered() {
            return {{ $items->map(fn($i) => ['id'=>$i->id,'name'=>$i->name,'unit'=>$i->unit,'qty'=>$i->current_qty])->values()->toJson() }}.filter(i =>
                i.name.toLowerCase().includes(this.search.toLowerCase())
            );
        }
    }" class="space-y-6">

        <x-bento-card>
            <form method="POST" action="{{ route('transfers.store') }}" id="transfer-form" class="space-y-5">
                @csrf

                <div>
                    <x-label for="to_dept_id">Transfer To Department *</x-label>
                    <x-select id="to_dept_id" name="to_dept_id" required>
                        <option value="">— Select destination —</option>
                        @foreach($departments as $dept)
                            @if(!$myDept || $dept->id !== $myDept->id)
                                <option value="{{ $dept->id }}" @selected(old('to_dept_id') == $dept->id)>{{ $dept->name }}</option>
                            @endif
                        @endforeach
                    </x-select>
                    @error('to_dept_id') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="purpose">Purpose *</x-label>
                    <textarea id="purpose" name="purpose" rows="2" required
                              class="w-full rounded-lg border border-surface-border bg-surface-tile text-ink-body px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                              placeholder="Reason for transfer…">{{ old('purpose') }}</textarea>
                    @error('purpose') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="notes">Notes (optional)</x-label>
                    <textarea id="notes" name="notes" rows="2"
                              class="w-full rounded-lg border border-surface-border bg-surface-tile text-ink-body px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                              placeholder="Additional notes…">{{ old('notes') }}</textarea>
                </div>
            </form>
        </x-bento-card>

        <div class="grid grid-cols-2 gap-6">
            {{-- Item picker --}}
            <x-bento-card>
                <p class="text-sm font-medium text-ink-heading mb-3">Available Items</p>
                <input type="text" x-model="search" placeholder="Search items…"
                       class="w-full rounded-lg border border-surface-border bg-surface-tile text-ink-body px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-1 focus:ring-primary-500"/>
                @if($items->isEmpty())
                    <p class="text-xs text-ink-muted italic">No items with available stock in your department.</p>
                @else
                    <div class="space-y-1 max-h-64 overflow-y-auto pr-1">
                        <template x-for="item in filtered" :key="item.id">
                            <button type="button"
                                    @click="addItem(item.id, item.name, item.unit, item.qty)"
                                    :disabled="selectedItems.find(i => i.id == item.id)"
                                    class="w-full text-left flex items-center justify-between px-3 py-2 rounded-lg text-sm hover:bg-primary-50 transition disabled:opacity-40 disabled:cursor-not-allowed">
                                <span class="font-medium text-ink-heading" x-text="item.name"></span>
                                <span class="text-xs text-ink-muted font-mono" x-text="item.qty + ' ' + item.unit"></span>
                            </button>
                        </template>
                    </div>
                @endif
            </x-bento-card>

            {{-- Selected items --}}
            <x-bento-card>
                <p class="text-sm font-medium text-ink-heading mb-3">Items to Transfer</p>
                <div x-show="selectedItems.length === 0" class="text-xs text-ink-muted italic">Select items from the left panel.</div>
                <div class="space-y-2">
                    <template x-for="(item, idx) in selectedItems" :key="item.id">
                        <div class="flex items-center gap-3 p-2 bg-surface-page rounded-lg">
                            <input type="hidden" :name="`items[${idx}][item_id]`" :value="item.id"/>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-ink-heading" x-text="item.name"></p>
                                <p class="text-xs text-ink-muted" x-text="item.unit"></p>
                            </div>
                            <input type="number" :name="`items[${idx}][qty]`" x-model.number="item.qty"
                                   :max="item.maxQty" min="1" required
                                   class="w-20 rounded border border-surface-border bg-surface-tile text-ink-body px-2 py-1 text-sm text-center focus:outline-none focus:ring-1 focus:ring-primary-500"/>
                            <button type="button" @click="removeItem(item.id)" class="text-rose-400 hover:text-rose-600">
                                <x-heroicon-o-trash class="w-4 h-4"/>
                            </button>
                        </div>
                    </template>
                </div>
            </x-bento-card>
        </div>

        <div class="flex justify-end gap-3">
            <x-button href="{{ route('transfers.index') }}" variant="ghost">Cancel</x-button>
            <x-button type="submit" form="transfer-form" variant="primary"
                      x-bind:disabled="selectedItems.length === 0">
                <x-heroicon-o-paper-airplane class="w-4 h-4"/>
                Submit for Head Approval
            </x-button>
        </div>
    </div>
</x-app-layout>
