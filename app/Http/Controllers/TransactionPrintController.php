<?php

namespace App\Http\Controllers;

use App\Models\Transaction;

class TransactionPrintController extends Controller
{
    public function show(Transaction $transaction)
    {
        $scope = $this->deptScope();
        if ($scope && $transaction->department_id !== $scope) {
            abort(403);
        }

        $transaction->load(['item', 'department', 'receivedBy', 'releasedBy', 'headApprovedBy']);

        return view('transactions.print', compact('transaction'));
    }
}
