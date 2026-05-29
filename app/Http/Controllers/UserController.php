<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('name')->get();

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        return view('users.create', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'          => ['required', Rule::in(['admin', 'staff', 'accounting'])],
            'password'      => ['required', 'confirmed', Password::min(8)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_head'       => ['boolean'],
        ]);

        User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'role'          => $data['role'],
            'password'      => Hash::make($data['password']),
            'is_active'     => true,
            'department_id' => $data['department_id'] ?? null,
            'is_head'       => $request->boolean('is_head'),
        ]);

        return redirect()->route('users.index')
            ->with('success', "User \"{$data['name']}\" created successfully.");
    }

    public function edit(User $user): View
    {
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        return view('users.edit', compact('user', 'departments'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'          => ['required', Rule::in(['admin', 'staff', 'accounting'])],
            'password'      => ['nullable', 'confirmed', Password::min(8)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_head'       => ['boolean'],
        ]);

        $user->name          = $data['name'];
        $user->email         = $data['email'];
        $user->role          = $data['role'];
        $user->department_id = $data['department_id'] ?? null;
        $user->is_head       = $request->boolean('is_head');

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()->route('users.index')
            ->with('success', "User \"{$user->name}\" updated successfully.");
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        // Prevent deactivating yourself
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $action = $user->is_active ? 'reactivated' : 'deactivated';

        return back()->with('success', "User \"{$user->name}\" has been {$action}.");
    }
}
