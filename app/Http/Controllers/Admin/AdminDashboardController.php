<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'locked_users' => User::where('status', 'locked')->count(),
            'suspended_users' => User::where('status', 'suspended')->count(),
        ];

        $recent_users = User::with('roles')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recent_logs = AuditLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'recent_users', 'recent_logs'));
    }
}
