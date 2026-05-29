<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $scope = $this->deptScope();
        $query = Transaction::when($scope, fn($q) => $q->where('department_id', $scope));

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->status) {
            $query->where('acknowledgment_status', $request->status);
        }

        if ($request->office) {
            $query->where('released_to_office', 'like', '%' . $request->office . '%');
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('item_name_snapshot', 'like', '%' . $request->search . '%')
                    ->orWhere('released_to_office', 'like', '%' . $request->search . '%')
                    ->orWhere('receiver_name', 'like', '%' . $request->search . '%')
                    ->orWhere('received_from', 'like', '%' . $request->search . '%');
            });
        }

        $transactions = $query->latest()->paginate(20);

        return view('transactions', compact('transactions'));
    }

    public function show(Transaction $transaction)
    {
        $scope = $this->deptScope();
        if ($scope && $transaction->department_id !== $scope) {
            abort(403);
        }
        return view('transactions-show', compact('transaction'));
    }
}