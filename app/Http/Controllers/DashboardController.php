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
        $scope = auth()->user()->departmentScope();

        $totalInStock = Item::where('current_qty', '>', 0)
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->count();

        $totalReleased = Transaction::where('type', 'released')
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->count();

        $pendingAck = Transaction::where('type', 'released')
            ->where('acknowledgment_status', 'pending')
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->count();

        $acknowledged = Transaction::where('type', 'released')
            ->where('acknowledgment_status', 'acknowledged')
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->count();

        $pendingTransactions = Transaction::where('type', 'released')
            ->where('acknowledgment_status', 'pending')
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->latest()
            ->limit(8)
            ->get();

        $weeklyActivity = collect(range(6, 0))->map(function ($daysAgo) use ($scope) {
            $date = Carbon::today()->subDays($daysAgo);
            return Transaction::where('type', 'released')
                ->whereDate('date_released', $date)
                ->when($scope, fn($q) => $q->where('department_id', $scope))
                ->count();
        })->all();

        $startOfMonth = Carbon::now()->startOfMonth();
        $topOffice = Transaction::where('type', 'released')
            ->where('date_released', '>=', $startOfMonth)
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->selectRaw('released_to_office, COUNT(*) as c')
            ->groupBy('released_to_office')
            ->orderByDesc('c')
            ->value('released_to_office');

        $topItem = Transaction::where('type', 'released')
            ->where('date_released', '>=', $startOfMonth)
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->selectRaw('item_name_snapshot, COUNT(*) as c')
            ->groupBy('item_name_snapshot')
            ->orderByDesc('c')
            ->value('item_name_snapshot');

        $pcThisMonth = PettyCashVoucher::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereIn('status', ['submitted', 'acknowledged', 'settled'])
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->sum('total_amount');

        $pcVouchersThisMonth = PettyCashVoucher::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->count();

        $pcPendingAck = PettyCashVoucher::where('status', 'submitted')
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->count();

        $pcPendingSettle = PettyCashVoucher::where('status', 'acknowledged')
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->count();

        $recentVouchers = PettyCashVoucher::with('creator')
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->latest()->limit(5)->get();

        $expiringItems = Item::whereNotNull('expiry_date')
            ->where('expiry_date', '<=', Carbon::today()->addDays(30))
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->orderBy('expiry_date')
            ->limit(8)
            ->get();

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
            'expiringItems',
        ));
    }
}
