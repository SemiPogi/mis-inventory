<x-app-layout>
    <x-page-header title="New IAR" subtitle="Record a supplier delivery for inspection and acceptance.">
        <x-slot name="actions">
            <x-button href="{{ route('iar.index') }}" variant="ghost">← Back</x-button>
        </x-slot>
    </x-page-header>

    <div x-data="{
        items: [{ item_name: '', unit: '', qty_delivered: 1, qty_accepted: 1, unit_cost: 0, description: '', remarks: '' }],
        addItem() { this.items.push({ item_name: '', unit: '', qty_delivered: 1, qty_accepted: 1, unit_cost: 0, description: '', remarks: '' }); },
        removeItem(i) { if (this.items.length > 1) this.items.splice(i, 1); }
    }" class="space-y-6">

        <x-bento-card>
            <form method="POST" action="{{ route('iar.store') }}" id="iar-form" class="space-y-5">
                @csrf

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-label for="supplier">Supplier / Vendor *</x-label>
                        <x-input id="supplier" name="supplier" value="{{ old('supplier') }}" required placeholder="Company or individual name"/>
                        @error('supplier') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-label for="purchase_order_no">Purchase Order No.</x-label>
                        <x-input id="purchase_order_no" name="purchase_order_no" value="{{ old('purchase_order_no') }}" placeholder="Optional"/>
                    </div>
                    <div>
                        <x-label for="date_of_delivery">Date of Delivery</x-label>
                        <x-input type="date" id="date_of_delivery" name="date_of_delivery" value="{{ old('date_of_delivery') }}"/>
                    </div>
                    <div>
                        <x-label for="date_of_inspection">Date of Inspection</x-label>
                        <x-input type="date" id="date_of_inspection" name="date_of_inspection" value="{{ old('date_of_inspection') }}"/>
                    </div>
                </div>

                <div>
                    <x-label for="notes">Notes (optional)</x-label>
                    <textarea id="notes" name="notes" rows="2"
                              class="w-full rounded-lg border border-surface-border bg-surface-tile text-ink-body px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                              placeholder="Inspection remarks or general notes…">{{ old('notes') }}</textarea>
                </div>
            </form>
        </x-bento-card>

        <x-bento-card>
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm font-medium text-ink-heading">Delivered Items</p>
                <button type="button" @click="addItem()"
                        class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700">
                    <x-heroicon-o-plus class="w-4 h-4"/> Add Item
                </button>
            </div>

            <div class="space-y-3">
                <template x-for="(item, i) in items" :key="i">
                    <div class="grid grid-cols-12 gap-2 items-start p-3 bg-surface-page rounded-lg">
                        <div class="col-span-3">
                            <label class="text-xs text-ink-muted mb-1 block">Item Name *</label>
                            <input type="text" :name="`items[${i}][item_name]`" x-model="item.item_name" required
                                   class="w-full rounded border border-surface-border bg-surface-tile text-ink-body px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500"/>
                        </div>
                        <div class="col-span-1">
                            <label class="text-xs text-ink-muted mb-1 block">Unit *</label>
                            <input type="text" :name="`items[${i}][unit]`" x-model="item.unit" required
                                   class="w-full rounded border border-surface-border bg-surface-tile text-ink-body px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500"/>
                        </div>
                        <div class="col-span-1">
                            <label class="text-xs text-ink-muted mb-1 block">Delivered</label>
                            <input type="number" :name="`items[${i}][qty_delivered]`" x-model.number="item.qty_delivered" min="0" required
                                   class="w-full rounded border border-surface-border bg-surface-tile text-ink-body px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500"/>
                        </div>
                        <div class="col-span-1">
                            <label class="text-xs text-ink-muted mb-1 block">Accepted</label>
                            <input type="number" :name="`items[${i}][qty_accepted]`" x-model.number="item.qty_accepted" min="0" required
                                   class="w-full rounded border border-surface-border bg-surface-tile text-ink-body px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500"/>
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs text-ink-muted mb-1 block">Unit Cost</label>
                            <input type="number" :name="`items[${i}][unit_cost]`" x-model.number="item.unit_cost" min="0" step="0.01"
                                   class="w-full rounded border border-surface-border bg-surface-tile text-ink-body px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500"/>
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs text-ink-muted mb-1 block">Description</label>
                            <input type="text" :name="`items[${i}][description]`" x-model="item.description"
                                   class="w-full rounded border border-surface-border bg-surface-tile text-ink-body px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500"/>
                        </div>
                        <div class="col-span-1">
                            <label class="text-xs text-ink-muted mb-1 block">Remarks</label>
                            <input type="text" :name="`items[${i}][remarks]`" x-model="item.remarks"
                                   class="w-full rounded border border-surface-border bg-surface-tile text-ink-body px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500"/>
                        </div>
                        <div class="col-span-1 flex items-end justify-center pb-1.5">
                            <button type="button" @click="removeItem(i)" x-show="items.length > 1"
                                    class="text-rose-400 hover:text-rose-600">
                                <x-heroicon-o-trash class="w-4 h-4"/>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </x-bento-card>

        <div class="flex justify-end gap-3">
            <x-button href="{{ route('iar.index') }}" variant="ghost">Cancel</x-button>
            <x-button type="submit" form="iar-form" variant="primary">
                <x-heroicon-o-document-check class="w-4 h-4"/> Save IAR as Draft
            </x-button>
        </div>
    </div>
</x-app-layout>
