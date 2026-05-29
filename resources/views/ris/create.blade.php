<x-app-layout>
    <x-page-header title="New RIS Request" subtitle="Request items from the Supply Department.">
        <x-slot name="actions">
            <x-button href="{{ route('ris.index') }}" variant="ghost">← Back</x-button>
        </x-slot>
    </x-page-header>

    <div x-data="{
        items: [{ stock_no: '', item_name: '', unit: 'pcs', requested_qty: 1, remarks: '' }],
        addItem() { this.items.push({ stock_no: '', item_name: '', unit: 'pcs', requested_qty: 1, remarks: '' }); },
        removeItem(i) { if (this.items.length > 1) this.items.splice(i, 1); }
    }" class="space-y-6">

        <x-bento-card>
            <form method="POST" action="{{ route('ris.store') }}" class="space-y-5" id="ris-form">
                @csrf

                <div>
                    <x-label for="purpose">Purpose / Justification</x-label>
                    <textarea id="purpose" name="purpose" rows="3"
                              class="w-full rounded-lg border border-surface-border bg-surface-tile text-ink-body px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                              placeholder="State the purpose of this requisition…" required>{{ old('purpose') }}</textarea>
                    @error('purpose') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="notes">Additional Notes (optional)</x-label>
                    <textarea id="notes" name="notes" rows="2"
                              class="w-full rounded-lg border border-surface-border bg-surface-tile text-ink-body px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                              placeholder="Any additional information…">{{ old('notes') }}</textarea>
                </div>
            </form>
        </x-bento-card>

        <x-bento-card>
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm font-medium text-ink-heading">Requested Items</p>
                <button type="button" @click="addItem()"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-primary-600 hover:text-primary-700">
                    <x-heroicon-o-plus class="w-4 h-4"/> Add Item
                </button>
            </div>

            <div class="space-y-3">
                <template x-for="(item, i) in items" :key="i">
                    <div class="grid grid-cols-12 gap-2 items-start p-3 bg-surface-page rounded-lg">
                        <div class="col-span-2">
                            <label class="text-xs text-ink-muted mb-1 block">Stock No.</label>
                            <input type="text" :name="`items[${i}][stock_no]`" x-model="item.stock_no"
                                   class="w-full rounded border border-surface-border bg-surface-tile text-ink-body px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500"
                                   placeholder="Optional"/>
                        </div>
                        <div class="col-span-4">
                            <label class="text-xs text-ink-muted mb-1 block">Item Name *</label>
                            <input type="text" :name="`items[${i}][item_name]`" x-model="item.item_name" required
                                   class="w-full rounded border border-surface-border bg-surface-tile text-ink-body px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500"
                                   placeholder="e.g. Bond Paper A4"/>
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs text-ink-muted mb-1 block">Unit *</label>
                            <input type="text" :name="`items[${i}][unit]`" x-model="item.unit" required
                                   class="w-full rounded border border-surface-border bg-surface-tile text-ink-body px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500"
                                   placeholder="pcs"/>
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs text-ink-muted mb-1 block">Qty *</label>
                            <input type="number" :name="`items[${i}][requested_qty]`" x-model.number="item.requested_qty" min="1" required
                                   class="w-full rounded border border-surface-border bg-surface-tile text-ink-body px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500"/>
                        </div>
                        <div class="col-span-1">
                            <label class="text-xs text-ink-muted mb-1 block">Remarks</label>
                            <input type="text" :name="`items[${i}][remarks]`" x-model="item.remarks"
                                   class="w-full rounded border border-surface-border bg-surface-tile text-ink-body px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500"
                                   placeholder=""/>
                        </div>
                        <div class="col-span-1 flex items-end justify-center pb-1.5">
                            <button type="button" @click="removeItem(i)" x-show="items.length > 1"
                                    class="text-rose-400 hover:text-rose-600 transition">
                                <x-heroicon-o-trash class="w-4 h-4"/>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </x-bento-card>

        <div class="flex justify-end gap-3">
            <x-button href="{{ route('ris.index') }}" variant="ghost">Cancel</x-button>
            <x-button type="submit" form="ris-form" variant="primary">
                <x-heroicon-o-paper-airplane class="w-4 h-4"/>
                Submit RIS for Approval
            </x-button>
        </div>
    </div>
</x-app-layout>
