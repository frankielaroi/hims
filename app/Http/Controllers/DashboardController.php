<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $roles = $user->roles->pluck('slug')->toArray();

        // Analytics Data (Only for relevant roles or general overview)
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'roles_count' => Role::count(),
        ];

        // Login Activity Analytics (Last 7 Days)
        $login_activity = AuditLog::where('action', 'login')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        $chart_data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $label = now()->subDays($i)->format('D');
            $chart_data['labels'][] = $label;
            $chart_data['data'][] = $login_activity[$date] ?? 0;
        }

        return view('dashboard', compact('user', 'roles', 'stats', 'chart_data'));
    }
}
