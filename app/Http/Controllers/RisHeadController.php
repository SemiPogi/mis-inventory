<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\RisRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Department Head approves or rejects RIS requests from their department.
 */
class RisHeadController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $pending = RisRequest::with(['requestedBy', 'requestingDept', 'items'])
            ->where('status', 'pending_head')
            ->when(! $user->isAdmin(), fn($q) => $q->where('requesting_dept_id', $user->department_id))
            ->latest()
            ->get();

        return view('ris.head-queue', compact('pending'));
    }

    public function approve(Request $request, RisRequest $ris): RedirectResponse
    {
        $this->authorizeHead($ris);

        if (! $ris->isPendingHead()) {
            return back()->with('error', 'This RIS is not awaiting head approval.');
        }

        $ris->update([
            'status'               => 'pending_supply',
            'head_approved_by_id'  => auth()->id(),
            'head_approved_at'     => now(),
        ]);

        // Notify requester
        Notification::notify($ris->requested_by_id, 'ris_approved',
            'RIS Approved',
            "{$ris->ris_number} was approved by the department head and sent to Supply.",
            ['url' => route('ris.show', $ris)]
        );

        return redirect()->route('ris.head.index')
            ->with('success', "{$ris->ris_number} approved and sent to Supply.");
    }

    public function reject(Request $request, RisRequest $ris): RedirectResponse
    {
        $this->authorizeHead($ris);

        $request->validate(['notes' => ['required', 'string', 'max:500']]);

        if (! $ris->isPendingHead()) {
            return back()->with('error', 'This RIS is not awaiting head approval.');
        }

        $ris->update([
            'status' => 'rejected',
            'notes'  => $request->notes,
        ]);

        Notification::notify($ris->requested_by_id, 'ris_rejected',
            'RIS Rejected',
            "{$ris->ris_number} was rejected: {$request->notes}",
            ['url' => route('ris.show', $ris)]
        );

        return redirect()->route('ris.head.index')
            ->with('success', "{$ris->ris_number} rejected.");
    }

    private function authorizeHead(RisRequest $ris): void
    {
        $user = auth()->user();
        // Admin can approve any RIS; dept heads can only approve their own dept
        if ($user->isAdmin()) return;
        if (! $user->is_head || $user->department_id !== $ris->requesting_dept_id) {
            abort(403, 'Only the department head can approve this RIS.');
        }
    }
}
