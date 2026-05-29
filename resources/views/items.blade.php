<x-app-layout>
    <x-page-header title="Inventory" subtitle="All items received by the MIS Office">
        <x-slot:actions>
            <x-button as="a" variant="primary" href="{{ route('receive.index') }}">
                <x-heroicon-o-plus class="w-4 h-4"/> Receive Item
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Filters --}}
    <x-bento-card class="mb-4">
        <form method="GET" action="{{ route('items.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div class="md:col-span-2">
                <x-label for="search">Search</x-label>
                <x-input id="search" name="search" :value="request('search')" placeholder="Item name or brand…"/>
            </div>
            <div>
                <x-label for="category">Category</x-label>
                <x-select id="category" name="category">
                    <option value="">All categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>
                    @endforeach
                </x-select>
            </div>
            <div class="flex gap-2">
                @if(request()->hasAny(['search','category']))
                    <x-button as="a" variant="ghost" href="{{ route('items.index') }}">Clear</x-button>
                @endif
                <x-button type="submit" variant="primary">
                    <x-heroicon-o-funnel class="w-4 h-4"/> Filter
                </x-button>
            </div>
        </form>
    </x-bento-card>

    {{-- Card grid --}}
    @if($items->isEmpty())
        <x-bento-card><x-empty-state icon="cube" title="No items found" hint="Adjust filters or receive a new item."/></x-bento-card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
             x-data x-init="$stagger($el)" data-anim="stagger">
            @foreach($items as $item)
                <a href="{{ route('items.show', $item->id) }}"
                   class="block bg-surface-tile rounded-2xl shadow-tile p-5 transition hover:-translate-y-1 hover:shadow-tile-hover">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-ink-heading truncate">{{ $item->name }}</p>
                            <p class="text-xs text-ink-muted mt-0.5 truncate">
                                {{ $item->brand ?? '—' }}{{ $item->category ? ' • '.$item->category : '' }}
                            </p>
                        </div>
                        <div class="flex flex-col items-end gap-1 shrink-0">
                            @if($item->current_qty > 0)
                                <span class="bg-emerald-50 text-emerald-700 text-xs font-medium px-2.5 py-1 rounded-full">In stock</span>
                            @else
                                <span class="bg-rose-50 text-rose-700 text-xs font-medium px-2.5 py-1 rounded-full">Out</span>
                            @endif
                            @if($item->expiryStatus() === 'expired')
                                <span class="bg-rose-100 text-rose-700 text-xs font-medium px-2 py-0.5 rounded-full">Expired</span>
                            @elseif($item->expiryStatus() === 'soon')
                                <span class="bg-amber-100 text-amber-700 text-xs font-medium px-2 py-0.5 rounded-full">Expires soon</span>
                            @endif
                        </div>
                    </div>
                    <div class="mt-4 flex items-end justify-between">
                        <div>
                            <p class="text-3xl font-bold text-ink-heading">{{ $item->current_qty }}</p>
                            <p class="text-xs text-ink-muted">{{ $item->unit }} on hand</p>
                        </div>
                        <div class="w-24 h-10">
                            <x-sparkline :data="$item->movement30"/>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $items->withQueryString()->links() }}
        </div>
    @endif
</x-app-layout>
