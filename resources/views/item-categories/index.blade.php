<x-app-layout>
    <x-page-header title="Item Categories" subtitle="Manage the categories available when receiving items."/>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif
    @if(session('error'))
        <x-toast type="error" :message="session('error')"/>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Add new category --}}
        <div>
            <x-bento-card>
                <p class="text-sm font-semibold text-ink-heading mb-4">Add New Category</p>
                <form method="POST" action="{{ route('item-categories.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-label for="name" required>Category Name</x-label>
                        <x-input id="name" name="name" :value="old('name')" placeholder="e.g. Medical Supplies" autofocus/>
                        @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <x-button type="submit" variant="primary" class="w-full justify-center">
                        <x-heroicon-o-plus class="w-4 h-4"/> Add Category
                    </x-button>
                </form>
            </x-bento-card>
        </div>

        {{-- Categories list --}}
        <div class="lg:col-span-2">
            <x-bento-card :padded="false">
                @if($categories->isEmpty())
                    <x-empty-state icon="tag" title="No categories yet" hint="Add your first category on the left."/>
                @else
                    <x-table :headers="['Category Name', 'Items Using', 'Status', '']">
                        @foreach($categories as $cat)
                            <x-table.row x-data="{ editing: false }">

                                {{-- View mode --}}
                                <td class="px-6 py-3" x-show="!editing">
                                    <span class="font-medium text-ink-heading text-sm">{{ $cat->name }}</span>
                                </td>

                                {{-- Edit mode --}}
                                <td class="px-6 py-3" x-show="editing" x-cloak>
                                    <form method="POST" action="{{ route('item-categories.update', $cat) }}" class="flex gap-2 items-center">
                                        @csrf @method('PATCH')
                                        <x-input name="name" :value="$cat->name" class="py-1 text-sm" required/>
                                        <x-button type="submit" variant="primary" class="py-1 text-xs shrink-0">Save</x-button>
                                        <button type="button" @click="editing = false" class="text-xs text-ink-muted hover:text-ink-body">Cancel</button>
                                    </form>
                                </td>

                                <td class="px-6 py-3 text-sm text-ink-muted" x-show="!editing">
                                    {{ \App\Models\Item::where('category', $cat->name)->count() }} items
                                </td>
                                <td class="px-6 py-3" x-show="!editing" x-cloak></td>

                                <td class="px-6 py-3" x-show="!editing">
                                    @if($cat->is_active)
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-rose-600">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span> Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-3" x-show="editing" x-cloak></td>

                                <td class="px-6 py-3 text-right" x-show="!editing">
                                    <div class="flex items-center justify-end gap-3">
                                        <button @click="editing = true" class="text-xs font-medium text-primary-600 hover:text-primary-700">Edit</button>

                                        <form method="POST" action="{{ route('item-categories.toggle', $cat) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="text-xs font-medium {{ $cat->is_active ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                                {{ $cat->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('item-categories.destroy', $cat) }}"
                                              onsubmit="return confirm('Delete this category?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs font-medium text-rose-500 hover:text-rose-700">Delete</button>
                                        </form>
                                    </div>
                                </td>
                                <td class="px-6 py-3" x-show="editing" x-cloak></td>

                            </x-table.row>
                        @endforeach
                    </x-table>
                @endif
            </x-bento-card>
        </div>
    </div>
</x-app-layout>
