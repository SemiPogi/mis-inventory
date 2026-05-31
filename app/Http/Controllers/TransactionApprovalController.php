<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionApprovalController extends Controller
{
    public function index(): View
    {
        $this->authorizeApprover();

        $user = auth()->user();

        $base = Transaction::with(['item', 'receivedBy', 'releasedBy', 'department'])
            ->where('head_approval_status', 'pending')
            ->when(! $user->isAdmin(), fn($q) => $q->where('department_id', $user->department_id))
            ->latest();

        $pendingReceives = (clone $base)->where('type', 'received')->get();
        $pendingReleases = (clone $base)->where('type', 'released')->get();

        return view('approvals.index', compact('pendingReceives', 'pendingReleases'));
    }

    public function approve(Transaction $transaction): RedirectResponse
    {
        $this->authorizeApproverFor($transaction);

        if (! $transaction->isPendingApproval()) {
            return back()->with('error', 'This transaction is not awaiting approval.');
        }

        $item = $transaction->item;

        if ($transaction->type === 'received') {
            $item->total_qty_received += $transaction->qty;
            $item->current_qty        += $transaction->qty;
            $item->save();
        } elseif ($transaction->type === 'released') {
            if ($item->current_qty < $transaction->qty) {
                return back()->with('error',
                    "Cannot approve: only {$item->current_qty} {$item->unit} available, but {$transaction->qty} requested.");
            }
            $item->current_qty -= $transaction->qty;
            $item->save();
        }

        $updates = [
            'head_approval_status' => 'approved',
            'head_approved_by_id'  => auth()->id(),
            'head_approved_at'     => now(),
        ];

        if ($transaction->type === 'released') {
            $updates['acknowledgment_status'] = 'pending';
        }

        $transaction->update($updates);

        return redirect()->route('approvals.index')
            ->with('success', 'Transaction approved.');
    }

    public function reject(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeApproverFor($transaction);

        $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        if (! $transaction->isPendingApproval()) {
            return back()->with('error', 'This transaction is not awaiting approval.');
        }

        $transaction->update([
            'head_approval_status' => 'rejected',
            'head_rejection_notes' => $request->notes,
        ]);

        return redirect()->route('approvals.index')
            ->with('success', 'Transaction rejected.');
    }

    private function authorizeApprover(): void
    {
        $user = auth()->user();
        if ($user->isAdmin() || $user->is_head) return;
        abort(403, 'Only department heads and admins can manage approvals.');
    }

    private function authorizeApproverFor(Transaction $transaction): void
    {
        $user = auth()->user();
        if ($user->isAdmin()) return;
        if ($user->is_head && $user->department_id === $transaction->department_id) return;
        abort(403, 'You are not authorized to approve this transaction.');
    }
}
