<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class AcknowledgeController extends Controller
{
    public function index()
    {
        $pending = Transaction::where('type', 'released')
            ->where('acknowledgment_status', 'pending')
            ->latest()
            ->get();

        $acknowledged = Transaction::where('type', 'released')
            ->where('acknowledgment_status', 'acknowledged')
            ->latest()
            ->get();

        return view('acknowledge', compact('pending', 'acknowledged'));
    }

    public function update(Request $request, Transaction $transaction)
    {
        $request->validate([
            'acknowledged_by_name' => 'required|string',
            'acknowledged_date' => 'required|date',
        ]);

        $transaction->update([
            'acknowledgment_status' => 'acknowledged',
            'acknowledged_by_name' => $request->acknowledged_by_name,
            'acknowledged_date' => $request->acknowledged_date,
            'acknowledgment_remarks' => $request->acknowledgment_remarks,
        ]);

        return redirect()->route('acknowledge.index')->with('success', 'Acknowledgment recorded successfully!');
    }
}