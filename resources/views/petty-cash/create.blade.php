<x-app-layout>
    <x-page-header title="New Petty Cash Voucher" subtitle="Record a petty cash purchase and update inventory.">
        <x-slot name="actions">
            <x-button href="{{ route('petty-cash.index') }}" variant="secondary">Cancel</x-button>
        </x-slot>
    </x-page-header>

    <form method="POST" action="{{ route('petty-cash.store') }}"
          x-data="{
              items: [{ item_name: '', qty: '', unit: '', unit_cost: '' }],
              transport: 0,
              requested: 0,
              get itemsTotal() {
                  return this.items.reduce((s, i) => s + (parseFloat(i.qty)||0) * (parseFloat(i.unit_cost)||0), 0);
              },
              get totalAmount() { return this.itemsTotal + (parseFloat(this.transport)||0); },
              get changeAmount() { return (parseFloat(this.requested)||0) - this.totalAmount; },
              addLine() { this.items.push({ item_name: '', qty: '', unit: '', unit_cost: '' }); },
              removeLine(idx) { if (this.items.length > 1) this.items.splice(idx, 1); },
          }">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Header info --}}
            <x-bento-card class="lg:col-span-2 space-y-4">
                <h2 class="text-sm font-semibold text-ink-heading uppercase tracking-wide">Voucher Details</h2>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-label for="date_purchased">Date Purchased</x-label>
                        <x-input type="date" id="date_purchased" name="date_purchased"
                                 value="{{ old('date_purchased', today()->toDateString()) }}" required />
                        @error('date_purchased') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-label for="or_number">Official Receipt No.</x-label>
                        <x-input id="or_number" name="or_number" value="{{ old('or_number') }}"
                                 placeholder="OR-12345" required />
                        @error('or_number') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-label for="store_name">Store / Supplier Name</x-label>
                        <x-input id="store_name" name="store_name" value="{{ old('store_name') }}"
                                 placeholder="National Bookstore" required />
                        @error('store_name') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-label for="releasing_officer">Releasing Officer</x-label>
                        <x-input id="releasing_officer" name="releasing_officer" value="{{ old('releasing_officer') }}"
                                 placeholder="Accounting officer name" required />
                        @error('releasing_officer') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <x-label for="remarks">Remarks (optional)</x-label>
                    <x-textarea id="remarks" name="remarks" rows="2">{{ old('remarks') }}</x-textarea>
                </div>
            </x-bento-card>

            {{-- Financial summary --}}
            <x-bento-card class="space-y-4">
                <h2 class="text-sm font-semibold text-ink-heading uppercase tracking-wide">Financials</h2>

                <div>
                    <x-label for="requested_amount">Amount Requested (max ₱2,000)</x-label>
                    <x-input type="number" id="requested_amount" name="requested_amount" step="0.01"
                             min="0.01" max="2000" x-model="requested"
                             value="{{ old('requested_amount') }}" required />
                    @error('requested_amount') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="transport_fee">Transport Fee (optional)</x-label>
                    <x-input type="number" id="transport_fee" name="transport_fee" step="0.01"
                             min="0" x-model="transport" value="{{ old('transport_fee', '0') }}" />
                </div>

                <div class="pt-2 border-t border-surface-border space-y-1 text-sm">
                    <div class="flex justify-between text-ink-muted">
                        <span>Items Total</span>
                        <span x-text="'₱' + itemsTotal.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between text-ink-muted">
                        <span>Transport</span>
                        <span x-text="'₱' + (parseFloat(transport)||0).toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between font-semibold text-ink-heading">
                        <span>Total Spent</span>
                        <span x-text="'₱' + totalAmount.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between font-bold text-lg"
                         :class="changeAmount >= 0 ? 'text-primary-700' : 'text-danger'">
                        <span>Change Due</span>
                        <span x-text="'₱' + changeAmount.toFixed(2)"></span>
                    </div>
                </div>

                @error('total') <p class="text-xs text-danger">{{ $message }}</p> @enderror

                <x-button type="submit" class="w-full" variant="primary">Submit Voucher</x-button>
            </x-bento-card>

            {{-- Line items --}}
            <x-bento-card class="lg:col-span-3">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-ink-heading uppercase tracking-wide">Items Purchased</h2>
                    <x-button type="button" variant="secondary" size="sm" @click="addLine()">+ Add Item</x-button>
                </div>

                <div class="space-y-3">
                    <template x-for="(item, idx) in items" :key="idx">
                        <div class="grid grid-cols-12 gap-3 items-end">
                            <div class="col-span-4">
                                <x-label>Item Name</x-label>
                                <input type="text" :name="`items[${idx}][item_name]`" x-model="item.item_name"
                                       placeholder="Bond Paper"
                                       class="w-full rounded-lg border border-surface-border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required />
                            </div>
                            <div class="col-span-2">
                                <x-label>Qty</x-label>
                                <input type="number" :name="`items[${idx}][qty]`" x-model="item.qty"
                                       step="0.01" min="0.01" placeholder="5"
                                       class="w-full rounded-lg border border-surface-border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required />
                            </div>
                            <div class="col-span-2">
                                <x-label>Unit</x-label>
                                <input type="text" :name="`items[${idx}][unit]`" x-model="item.unit"
                                       placeholder="reams"
                                       class="w-full rounded-lg border border-surface-border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required />
                            </div>
                            <div class="col-span-2">
                                <x-label>Unit Cost (₱)</x-label>
                                <input type="number" :name="`items[${idx}][unit_cost]`" x-model="item.unit_cost"
                                       step="0.01" min="0.01" placeholder="200"
                                       class="w-full rounded-lg border border-surface-border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required />
                            </div>
                            <div class="col-span-1 text-sm text-ink-muted pb-2">
                                <span x-text="'₱' + ((parseFloat(item.qty)||0)*(parseFloat(item.unit_cost)||0)).toFixed(2)"></span>
                            </div>
                            <div class="col-span-1 pb-1">
                                <button type="button" @click="removeLine(idx)"
                                        class="text-danger hover:text-rose-700 transition" title="Remove">
                                    <x-heroicon-o-trash class="w-5 h-5"/>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </x-bento-card>

        </div>
    </form>
</x-app-layout>
