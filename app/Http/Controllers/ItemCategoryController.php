<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItemCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ItemCategory::orderBy('name')->get();
        return view('item-categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:item_categories,name',
        ]);

        ItemCategory::create(['name' => $data['name'], 'is_active' => true]);

        return redirect()->route('item-categories.index')
            ->with('success', "Category \"{$data['name']}\" added.");
    }

    public function update(Request $request, ItemCategory $itemCategory): RedirectResponse
    {
        $data = $request->validate([
            'name' => "required|string|max:100|unique:item_categories,name,{$itemCategory->id}",
        ]);

        $itemCategory->update(['name' => $data['name']]);

        return redirect()->route('item-categories.index')
            ->with('success', "Category updated to \"{$data['name']}\".");
    }

    public function toggle(ItemCategory $itemCategory): RedirectResponse
    {
        $itemCategory->update(['is_active' => ! $itemCategory->is_active]);

        return redirect()->route('item-categories.index')
            ->with('success', "Category \"{$itemCategory->name}\" " . ($itemCategory->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function destroy(ItemCategory $itemCategory): RedirectResponse
    {
        $inUse = Item::where('category', $itemCategory->name)->exists();

        if ($inUse) {
            return redirect()->route('item-categories.index')
                ->with('error', "Cannot delete \"{$itemCategory->name}\" — it is used by existing items.");
        }

        $itemCategory->delete();

        return redirect()->route('item-categories.index')
            ->with('success', "Category \"{$itemCategory->name}\" deleted.");
    }
}
