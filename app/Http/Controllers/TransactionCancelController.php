<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TransactionCancelController extends Controller
{
    /**
     * Cancel the submitter's own pending transaction.
     * If it's a receive and the linked item has no other active transactions
     * and a current_qty of 0, the item record is also deleted.
     */
    public function cancel(Transaction $transaction): RedirectResponse
    {
        $this->authorizeSubmitter($transaction);

        if (! $transaction->isPendingApproval()) {
            return back()->with('error', 'Only pending submissions can be cancelled.');
        }

        $transaction->update(['head_approval_status' => 'cancelled']);

        // Receive-only: clean up an item that was created solely by this submission.
        // Because transactions.item_id has a non-nullable FK to items, we delete all
        // referencing cancelled transactions for that item first, then the item.
        if ($transaction->type === 'received') {
            $item = $transaction->item;

            if ($item && $item->current_qty === 0) {
                $otherActiveCount = Transaction::where('item_id', $item->id)
                    ->where('id', '!=', $transaction->id)
                    ->where(fn($q) => $q
                        ->whereNull('head_approval_status')
                        ->orWhere('head_approval_status', '!=', 'cancelled')
                    )
                    ->count();

                if ($otherActiveCount === 0) {
                    // Nullify item_id on all transactions referencing this item before
                    // deleting it. item_name_snapshot preserves the display name.
                    DB::table('transactions')
                        ->where('item_id', $item->id)
                        ->update(['item_id' => null]);

                    $item->delete();
                }
            }
        }

        return redirect()->route('transactions.index')
            ->with('success', 'Your submission has been cancelled.');
    }

    /**
     * Redirect to the appropriate form pre-filled with the rejected
     * transaction's data so the user can submit a corrected version.
     */
    public function resubmit(Transaction $transaction): RedirectResponse
    {
        $this->authorizeSubmitter($transaction);

        if (! $transaction->isRejected()) {
            return back()->with('error', 'Only rejected submissions can be re-submitted.');
        }

        if ($transaction->type === 'received') {
            $params = array_filter([
                'name'           => $transaction->item_name_snapshot,
                'qty'            => $transaction->qty,
                'unit'           => $transaction->unit,
                'received_from'  => $transaction->received_from,
                'ris_iar_number' => $transaction->ris_iar_number,
                'remarks'        => $transaction->remarks,
            ], fn($v) => $v !== null && $v !== '');

            return redirect()->route('receive.index', $params);
        }

        $params = array_filter([
            'item_id'              => $transaction->item_id,
            'qty'                  => $transaction->qty,
            'released_to_office'   => $transaction->released_to_office,
            'receiver_name'        => $transaction->receiver_name,
            'receiver_designation' => $transaction->receiver_designation,
            'purpose'              => $transaction->purpose,
            'date_released'        => $transaction->date_released,
            'remarks'              => $transaction->remarks,
        ], fn($v) => $v !== null && $v !== '');

        return redirect()->route('release.index', $params);
    }

    /** Abort 403 unless the auth user is the original submitter. */
    private function authorizeSubmitter(Transaction $transaction): void
    {
        $submitterId = $transaction->type === 'received'
            ? $transaction->received_by_user_id
            : $transaction->released_by_user_id;

        if ($submitterId !== auth()->id()) {
            abort(403, 'You can only manage your own submissions.');
        }
    }
}
