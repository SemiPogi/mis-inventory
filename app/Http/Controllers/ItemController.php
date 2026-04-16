<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $query = Item::query();

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('brand', 'like', '%' . $request->search . '%');
        }

        if ($request->category) {
            $query->where('category', $request->category);
        }

        $items = $query->latest()->paginate(20);

        $categories = Item::select('category')
            ->distinct()
            ->whereNotNull('category')
            ->pluck('category');

        return view('items', compact('items', 'categories'));
    }

    public function show(Item $item)
    {
        $transactions = $item->transactions()->latest()->get();
        return view('items-show', compact('item', 'transactions'));
    }
}