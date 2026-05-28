<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PettyCashVoucher;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $totalInStock = Item::where('current_qty', '>', 0)->count();
        $totalReleased = Transaction::where('type', 'released')->count();
        $pendingAck = Transaction::where('type', 'released')
            ->where('acknowledgment_status', 'pending')->count();
        $acknowledged = Transaction::where('type', 'released')
            ->where('acknowledgment_status', 'acknowledged')->count();

        $pendingTransactions = Transaction::where('type', 'released')
            ->where('acknowledgment_status', 'pending')
            ->latest()
            ->limit(8)
            ->get();

        $weeklyActivity = collect(range(6, 0))->map(function ($daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);
            return Transaction::where('type', 'released')
                ->whereDate('date_released', $date)
                ->count();
        })->all();

        $startOfMonth = Carbon::now()->startOfMonth();
        $topOffice = Transaction::where('type', 'released')
            ->where('date_released', '>=', $startOfMonth)
            ->selectRaw('released_to_office, COUNT(*) as c')
            ->groupBy('released_to_office')
            ->orderByDesc('c')
            ->value('released_to_office');

        $topItem = Transaction::where('type', 'released')
            ->where('date_released', '>=', $startOfMonth)
            ->selectRaw('item_name_snapshot, COUNT(*) as c')
            ->groupBy('item_name_snapshot')
            ->orderByDesc('c')
            ->value('item_name_snapshot');

        $pcThisMonth = PettyCashVoucher::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereIn('status', ['submitted', 'acknowledged', 'settled'])
            ->sum('total_amount');

        $pcVouchersThisMonth = PettyCashVoucher::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $pcPendingAck    = PettyCashVoucher::where('status', 'submitted')->count();
        $pcPendingSettle = PettyCashVoucher::where('status', 'acknowledged')->count();

        $recentVouchers = PettyCashVoucher::with('creator')->latest()->limit(5)->get();

        return view('dashboard', compact(
            'totalInStock',
            'totalReleased',
            'pendingAck',
            'acknowledged',
            'pendingTransactions',
            'weeklyActivity',
            'topOffice',
            'topItem',
            'pcThisMonth',
            'pcVouchersThisMonth',
            'pcPendingAck',
            'pcPendingSettle',
            'recentVouchers',
        ));
    }
}
