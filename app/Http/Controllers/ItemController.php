<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $scope = $this->deptScope();
        $query = Item::when($scope, fn($q) => $q->where('department_id', $scope));

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('brand', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->category) {
            $query->where('category', $request->category);
        }

        $items = $query->latest()->paginate(24);

        $items->getCollection()->transform(function (Item $item) {
            $item->movement30 = $this->movement30($item);
            return $item;
        });

        $categories = ItemCategory::active()->orderBy('name')->pluck('name');

        return view('items', compact('items', 'categories'));
    }

    public function show(Item $item)
    {
        $scope = $this->deptScope();
        if ($scope && $item->department_id !== $scope) {
            abort(403);
        }
        $transactions = $item->transactions()->latest()->get();
        $movement30   = $this->movement30($item);
        $logs         = $item->logs()->with('user')->get();
        return view('items-show', compact('item', 'transactions', 'movement30', 'logs'));
    }

    private function movement30(Item $item): array
    {
        $start = Carbon::today()->subDays(29);

        $rows = Transaction::where('item_id', $item->id)
            ->whereDate('created_at', '>=', $start)
            ->get(['type', 'qty', 'created_at']);

        $byDay = [];
        for ($i = 0; $i < 30; $i++) {
            $byDay[$start->copy()->addDays($i)->toDateString()] = 0;
        }
        foreach ($rows as $r) {
            $day = $r->created_at->toDateString();
            if (!array_key_exists($day, $byDay)) continue;
            $byDay[$day] += $r->type === 'received' ? $r->qty : -$r->qty;
        }
        return array_values($byDay);
    }
}
