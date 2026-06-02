<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $this->notifyApproval($transaction);

        $successMsg = $transaction->type === 'received'
            ? "Approved — {$transaction->qty} {$transaction->unit} of \"{$transaction->item_name_snapshot}\" added to inventory."
            : "Approved — {$transaction->qty} {$transaction->unit} of \"{$transaction->item_name_snapshot}\" deducted from inventory. Awaiting acknowledgment.";

        return redirect()->route('approvals.index')
            ->with('success', $successMsg);
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

        $submitterId = $transaction->type === 'received'
            ? $transaction->received_by_user_id
            : $transaction->released_by_user_id;

        $txKind = $transaction->type === 'received' ? 'receive' : 'release';

        if ($submitterId) {
            Notification::notify(
                $submitterId,
                'tx_rejected',
                'Request Rejected',
                "Your {$txKind} request for \"{$transaction->item_name_snapshot}\" was rejected. Reason: {$request->notes}",
                ['url' => route('transactions.show', $transaction)]
            );
        }

        return redirect()->route('approvals.index')
            ->with('success', 'Transaction rejected.');
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $this->authorizeApprover();

        $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:transactions,id'],
        ]);

        $ids          = $request->ids;
        $transactions = Transaction::with(['item', 'department'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $user     = auth()->user();
        $approved = 0;
        $failed   = [];

        foreach ($ids as $id) {
            $transaction = $transactions->get($id);

            if (! $transaction || ! $transaction->isPendingApproval()) {
                $failed[] = "Transaction #{$id}: not pending";
                continue;
            }

            // Scope check (mirrors authorizeApproverFor)
            if (! $user->isAdmin() && ! ($user->is_head && $user->department_id === $transaction->department_id)) {
                $failed[] = "\"{$transaction->item_name_snapshot}\": not in your scope";
                continue;
            }

            $item = $transaction->item;

            $updates = [
                'head_approval_status' => 'approved',
                'head_approved_by_id'  => auth()->id(),
                'head_approved_at'     => now(),
            ];

            if ($transaction->type === 'received') {
                $item->total_qty_received += $transaction->qty;
                $item->current_qty        += $transaction->qty;

                DB::transaction(function () use ($item, $transaction, $updates) {
                    $item->save();
                    $transaction->update($updates);
                });
            } elseif ($transaction->type === 'released') {
                if ($item->current_qty < $transaction->qty) {
                    $failed[] = "\"{$transaction->item_name_snapshot}\": insufficient stock ({$item->current_qty} {$item->unit} available)";
                    continue;
                }
                $item->current_qty -= $transaction->qty;
                $updates['acknowledgment_status'] = 'pending';

                DB::transaction(function () use ($item, $transaction, $updates) {
                    $item->save();
                    $transaction->update($updates);
                });
            }

            $this->notifyApproval($transaction);

            $approved++;
        }

        $redirect = redirect()->route('approvals.index');

        if ($approved > 0) {
            $word = $approved === 1 ? 'transaction' : 'transactions';
            $redirect = $redirect->with('success', "{$approved} {$word} approved successfully.");
        }

        if (! empty($failed)) {
            $redirect = $redirect->with('warning', 'Some items could not be approved: ' . implode('; ', $failed));
        }

        return $redirect;
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

    private function notifyApproval(Transaction $transaction): void
    {
        $submitterId = $transaction->type === 'received'
            ? $transaction->received_by_user_id
            : $transaction->released_by_user_id;

        if (! $submitterId) {
            return;
        }

        $notifType  = $transaction->type === 'received' ? 'tx_approved_receive' : 'tx_approved_release';
        $notifTitle = $transaction->type === 'received'
            ? 'Receive Approved — Collect from Supply'
            : 'Release Approved';
        $notifBody  = $transaction->type === 'received'
            ? "Your receive request for {$transaction->qty} {$transaction->unit} of \"{$transaction->item_name_snapshot}\" was approved. Items have been added to inventory. Please collect from the Supply Department."
            : "Your release request for {$transaction->qty} {$transaction->unit} of \"{$transaction->item_name_snapshot}\" was approved and inventory has been updated.";

        Notification::notify(
            $submitterId,
            $notifType,
            $notifTitle,
            $notifBody,
            ['url' => route('transactions.show', $transaction)]
        );
    }
}
