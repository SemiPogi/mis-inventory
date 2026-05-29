<x-app-layout>
    <x-page-header title="Record Assembly" subtitle="Combine component items to create a new assembled item.">
        <x-slot name="actions">
            <x-button href="{{ route('assemblies.index') }}" variant="ghost">← Back</x-button>
        </x-slot>
    </x-page-header>

    @if($errors->any())
        <x-bento-card>
            <ul class="text-sm text-danger space-y-1">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </x-bento-card>
    @endif

    <div x-data="{
        components: [],
        search: '',
        addComponent(id, name, unit, maxQty) {
            if (this.components.find(c => c.id == id)) return;
            this.components.push({ id, name, unit, maxQty, qty_used: 1 });
        },
        removeComponent(id) { this.components = this.components.filter(c => c.id != id); },
        get filteredItems() {
            return {{ $items->map(fn($i) => ['id'=>$i->id,'name'=>$i->name,'unit'=>$i->unit,'qty'=>$i->current_qty])->values()->toJson() }}
                .filter(i => i.name.toLowerCase().includes(this.search.toLowerCase()));
        }
    }" class="space-y-6">

        <x-bento-card>
            <form method="POST" action="{{ route('assemblies.store') }}" id="asm-form" class="space-y-5">
                @csrf
                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <x-label for="output_item_name">Output Item Name *</x-label>
                        <x-input id="output_item_name" name="output_item_name" value="{{ old('output_item_name') }}"
                                 placeholder="e.g. Assembled Wheelchair" required/>
                        @error('output_item_name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-label for="output_unit">Unit *</x-label>
                        <x-input id="output_unit" name="output_unit" value="{{ old('output_unit', 'unit') }}" required/>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-label for="qty_produced">Qty Produced *</x-label>
                        <x-input type="number" id="qty_produced" name="qty_produced" value="{{ old('qty_produced', 1) }}" min="1" required/>
                    </div>
                </div>
                <div>
                    <x-label for="notes">Notes (optional)</x-label>
                    <textarea id="notes" name="notes" rows="2"
                              class="w-full rounded-lg border border-surface-border bg-surface-tile text-ink-body px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">{{ old('notes') }}</textarea>
                </div>
            </form>
        </x-bento-card>

        <div class="grid grid-cols-2 gap-6">
            <x-bento-card>
                <p class="text-sm font-medium text-ink-heading mb-3">Components (pick from inventory)</p>
                <input type="text" x-model="search" placeholder="Search…"
                       class="w-full rounded-lg border border-surface-border bg-surface-tile text-ink-body px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-1 focus:ring-primary-500"/>
                @if($items->isEmpty())
                    <p class="text-xs text-ink-muted italic">No items with stock available.</p>
                @else
                    <div class="space-y-1 max-h-60 overflow-y-auto">
                        <template x-for="item in filteredItems" :key="item.id">
                            <button type="button" @click="addComponent(item.id, item.name, item.unit, item.qty)"
                                    :disabled="components.find(c => c.id == item.id)"
                                    class="w-full text-left flex items-center justify-between px-3 py-2 rounded text-sm hover:bg-primary-50 transition disabled:opacity-40">
                                <span class="font-medium text-ink-heading truncate" x-text="item.name"></span>
                                <span class="text-xs text-ink-muted font-mono ml-2 shrink-0" x-text="item.qty + ' ' + item.unit"></span>
                            </button>
                        </template>
                    </div>
                @endif
            </x-bento-card>

            <x-bento-card>
                <p class="text-sm font-medium text-ink-heading mb-3">Selected Components</p>
                <div x-show="components.length === 0" class="text-xs text-ink-muted italic">No components selected.</div>
                <div class="space-y-2">
                    <template x-for="(comp, idx) in components" :key="comp.id">
                        <div class="flex items-center gap-3 p-2 bg-surface-page rounded-lg">
                            <input type="hidden" :name="`components[${idx}][item_id]`" :value="comp.id"/>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-ink-heading truncate" x-text="comp.name"></p>
                                <p class="text-xs text-ink-muted" x-text="'max ' + comp.maxQty + ' ' + comp.unit"></p>
                            </div>
                            <input type="number" :name="`components[${idx}][qty_used]`" x-model.number="comp.qty_used"
                                   :max="comp.maxQty" min="1" required
                                   class="w-20 rounded border border-surface-border bg-surface-tile text-ink-body px-2 py-1 text-sm text-center"/>
                            <button type="button" @click="removeComponent(comp.id)" class="text-rose-400 hover:text-rose-600">
                                <x-heroicon-o-trash class="w-4 h-4"/>
                            </button>
                        </div>
                    </template>
                </div>
            </x-bento-card>
        </div>

        <div class="flex justify-end gap-3">
            <x-button href="{{ route('assemblies.index') }}" variant="ghost">Cancel</x-button>
            <x-button type="submit" form="asm-form" variant="primary"
                      x-bind:disabled="components.length === 0">
                <x-heroicon-o-wrench-screwdriver class="w-4 h-4"/> Record Assembly
            </x-button>
        </div>
    </div>
</x-app-layout>
