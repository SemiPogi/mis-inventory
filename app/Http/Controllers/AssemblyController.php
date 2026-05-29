<?php

namespace App\Http\Controllers;

use App\Models\Assembly;
use App\Models\AssemblyComponent;
use App\Models\Item;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Assembly: combine multiple items into a new assembled item.
 * Components are deducted from inventory; the assembled output is added.
 */
class AssemblyController extends Controller
{
    public function index(): View
    {
        $scope = $this->deptScope();
        $assemblies = Assembly::with(['department', 'assembledBy', 'components'])
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->latest()
            ->paginate(20);

        return view('assemblies.index', compact('assemblies'));
    }

    public function create(): View
    {
        $scope = $this->deptScope();
        $items = Item::when($scope, fn($q) => $q->where('department_id', $scope))
            ->where('current_qty', '>', 0)
            ->orderBy('name')
            ->get();

        return view('assemblies.create', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $scope = $this->deptScope();
        $deptId = $scope ?? auth()->user()->department_id;

        $data = $request->validate([
            'output_item_name'          => ['required', 'string', 'max:255'],
            'output_unit'               => ['required', 'string', 'max:50'],
            'qty_produced'              => ['required', 'integer', 'min:1'],
            'notes'                     => ['nullable', 'string', 'max:1000'],
            'components'                => ['required', 'array', 'min:1'],
            'components.*.item_id'      => ['required', 'exists:items,id'],
            'components.*.qty_used'     => ['required', 'integer', 'min:1'],
        ]);

        // Validate each component has enough stock
        foreach ($data['components'] as $line) {
            $item = Item::find($line['item_id']);
            if (! $item || $item->department_id !== $deptId) {
                return back()->withErrors(['components' => "Item #{$line['item_id']} does not belong to your department."])->withInput();
            }
            if ($item->current_qty < $line['qty_used']) {
                return back()->withErrors(['components' => "Insufficient stock for '{$item->name}'."])->withInput();
            }
        }

        $assembly = Assembly::create([
            'assembly_number' => Assembly::generateAssemblyNumber(),
            'department_id'   => $deptId,
            'output_item_name'=> $data['output_item_name'],
            'output_unit'     => $data['output_unit'],
            'qty_produced'    => $data['qty_produced'],
            'notes'           => $data['notes'] ?? null,
            'assembled_by_id' => auth()->id(),
            'assembled_at'    => now(),
        ]);

        // Deduct components and record audit
        foreach ($data['components'] as $line) {
            $item = Item::find($line['item_id']);

            AssemblyComponent::create([
                'assembly_id'       => $assembly->id,
                'item_id'           => $item->id,
                'item_name_snapshot'=> $item->name,
                'unit'              => $item->unit,
                'qty_used'          => $line['qty_used'],
            ]);

            $item->current_qty -= $line['qty_used'];
            $item->save();

            Transaction::create([
                'type'               => 'released',
                'item_id'            => $item->id,
                'item_name_snapshot' => $item->name,
                'qty'                => $line['qty_used'],
                'unit'               => $item->unit,
                'released_to'        => "Assembly {$assembly->assembly_number}",
                'date_released'      => now()->toDateString(),
                'released_by_user_id'=> auth()->id(),
                'department_id'      => $deptId,
            ]);
        }

        // Add assembled output item to dept inventory
        $existing = Item::where('name', $data['output_item_name'])
            ->where('department_id', $deptId)
            ->first();

        if ($existing) {
            $existing->total_qty_received += $data['qty_produced'];
            $existing->current_qty        += $data['qty_produced'];
            $existing->save();
            $outputItemId = $existing->id;
        } else {
            $newItem = Item::create([
                'name'               => $data['output_item_name'],
                'unit'               => $data['output_unit'],
                'total_qty_received' => $data['qty_produced'],
                'current_qty'        => $data['qty_produced'],
                'department_id'      => $deptId,
            ]);
            $outputItemId = $newItem->id;
        }

        Transaction::create([
            'type'               => 'received',
            'item_id'            => $outputItemId,
            'item_name_snapshot' => $data['output_item_name'],
            'qty'                => $data['qty_produced'],
            'unit'               => $data['output_unit'],
            'received_from'      => "Assembly {$assembly->assembly_number}",
            'date_received'      => now()->toDateString(),
            'received_by_user_id'=> auth()->id(),
            'acknowledgment_status' => 'acknowledged',
            'department_id'      => $deptId,
        ]);

        return redirect()->route('assemblies.show', $assembly)
            ->with('success', "{$assembly->assembly_number} recorded. Items deducted and output added to inventory.");
    }

    public function show(Assembly $assembly): View
    {
        $scope = $this->deptScope();
        if ($scope && $assembly->department_id !== $scope) {
            abort(403);
        }
        $assembly->load(['department', 'assembledBy', 'components.item', 'attachments.uploadedBy']);
        return view('assemblies.show', compact('assembly'));
    }
}
