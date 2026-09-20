<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    public function index()
    {
        $staff = User::with('roles')->latest()->paginate(20);

        return view('admin.staff.index', compact('staff'));
    }

    public function create()
    {
        $roles = Role::all();
        $departments = Department::where('is_active', true)->orderBy('name')->get();

        return view('admin.staff.create', compact('roles', 'departments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username|max:255',
            'email' => 'nullable|email|unique:users,email|max:255',
            'password' => ['required', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
            'status' => 'required|in:active,suspended,disabled',
            'department_id' => 'nullable|exists:departments,id',
            'contact_info' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'],
            'department_id' => $validated['department_id'] ?? null,
            'contact_info' => $validated['contact_info'] ?? null,
            'requires_password_change' => true, // Force change on first login
        ]);

        $user->roles()->attach($validated['roles']);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'staff_created',
            'description' => "Created staff account: {$user->username}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['new_user_id' => $user->id],
        ]);

        return redirect()->route('admin.staff.index')
            ->with('success', "Staff account for {$user->name} created successfully. User will be forced to change password on first login.");
    }
}
