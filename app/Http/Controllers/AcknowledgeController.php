<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class AcknowledgeController extends Controller
{
    public function index()
    {
        $scope = $this->deptScope();

        $pending = Transaction::where('type', 'released')
            ->where('acknowledgment_status', 'pending')
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->latest()
            ->get();

        $acknowledged = Transaction::whereIn('type', ['released', 'received'])
            ->where('acknowledgment_status', 'acknowledged')
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->latest()
            ->get();

        return view('acknowledge', compact('pending', 'acknowledged'));
    }

    public function update(Request $request, Transaction $transaction)
    {
        $scope = $this->deptScope();
        if ($scope && $transaction->department_id !== $scope) {
            abort(403);
        }

        $request->validate([
            'acknowledged_by_name' => 'required|string',
            'acknowledged_date'    => 'required|date',
        ]);

        $transaction->update([
            'acknowledgment_status'  => 'acknowledged',
            'acknowledged_by_name'   => $request->input('acknowledged_by_name'),
            'acknowledged_date'      => $request->input('acknowledged_date'),
            'acknowledgment_remarks' => $request->input('acknowledgment_remarks'),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('acknowledge.index')->with('success', 'Acknowledgment recorded successfully!');
    }
}