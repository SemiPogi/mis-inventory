<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $departments = Department::withCount('users')->orderBy('name')->get();
        return view('departments.index', compact('departments'));
    }

    public function create(): View
    {
        return view('departments.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'                       => ['required', 'string', 'max:255'],
            'code'                       => ['required', 'string', 'max:20', 'unique:departments,code'],
            'responsibility_center_code' => ['nullable', 'string', 'max:50'],
            'is_supply_hub'              => ['boolean'],
        ]);

        // Only one supply hub allowed
        if ($request->boolean('is_supply_hub')) {
            Department::where('is_supply_hub', true)->update(['is_supply_hub' => false]);
        }

        Department::create($data + [
            'is_active'     => true,
            'is_supply_hub' => $request->boolean('is_supply_hub'),
        ]);

        return redirect()->route('departments.index')
            ->with('success', "Department \"{$data['name']}\" created.");
    }

    public function edit(Department $department): View
    {
        return view('departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'name'                       => ['required', 'string', 'max:255'],
            'code'                       => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($department->id)],
            'responsibility_center_code' => ['nullable', 'string', 'max:50'],
            'is_supply_hub'              => ['boolean'],
        ]);

        if ($request->boolean('is_supply_hub') && ! $department->is_supply_hub) {
            Department::where('is_supply_hub', true)->update(['is_supply_hub' => false]);
        }

        $department->update($data + ['is_supply_hub' => $request->boolean('is_supply_hub')]);

        return redirect()->route('departments.index')
            ->with('success', "Department \"{$department->name}\" updated.");
    }

    public function toggle(Department $department): RedirectResponse
    {
        $department->update(['is_active' => ! $department->is_active]);
        $action = $department->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "\"{$department->name}\" {$action}.");
    }
}
