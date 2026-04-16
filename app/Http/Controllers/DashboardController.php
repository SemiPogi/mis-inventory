<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Transaction;

class DashboardController extends Controller
{
    public function index()
    {
        $totalInStock = Item::where('current_qty', '>', 0)->count();
        $totalReleased = Transaction::where('type', 'released')->count();
        $pendingAck = Transaction::where('type', 'released')
            ->where('acknowledgment_status', 'pending')
            ->count();
        $acknowledged = Transaction::where('type', 'released')
            ->where('acknowledgment_status', 'acknowledged')
            ->count();

        $pendingTransactions = Transaction::where('type', 'released')
            ->where('acknowledgment_status', 'pending')
            ->latest()
            ->get();

        return view('dashboard', compact(
            'totalInStock',
            'totalReleased',
            'pendingAck',
            'acknowledged',
            'pendingTransactions'
        ));
    }
}