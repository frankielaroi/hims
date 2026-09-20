@extends('layouts.app')

@section('title', 'Admin Management')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-100 tracking-tight">Staff & Settings</h1>
            <p class="text-slate-400 mt-1">Manage who has access and how the facility runs.</p>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('admin.staff.create') }}" class="bg-emerald-600 text-white px-5 py-2.5 rounded-lg font-semibold text-sm hover:bg-emerald-500 shadow-lg shadow-emerald-950/50 transition-all duration-200 flex items-center">
                <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                Add team member
            </a>
            <button class="bg-slate-800 text-slate-200 px-5 py-2.5 rounded-lg font-semibold text-sm border border-slate-700 hover:bg-slate-700 transition-all duration-200">
                Audit logs
            </button>
        </div>
    </div>

    <!-- Admin Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-slate-900 p-5 rounded-lg border border-slate-800">
            <p class="text-sm font-medium text-slate-400 mb-1">Active accounts</p>
            <p class="text-2xl font-bold text-emerald-400">{{ $stats['active_users'] }}</p>
        </div>
        <div class="bg-slate-900 p-5 rounded-lg border border-slate-800">
            <p class="text-sm font-medium text-slate-400 mb-1">Locked out</p>
            <p class="text-2xl font-bold text-rose-400">{{ $stats['locked_users'] }}</p>
        </div>
        <div class="bg-slate-900 p-5 rounded-lg border border-slate-800">
            <p class="text-sm font-medium text-slate-400 mb-1">Suspended</p>
            <p class="text-2xl font-bold text-amber-400">{{ $stats['suspended_users'] }}</p>
        </div>
        <div class="bg-slate-900 p-5 rounded-lg border border-slate-800">
            <p class="text-sm font-medium text-slate-400 mb-1">Total team members</p>
            <p class="text-2xl font-bold text-slate-100">{{ $stats['total_users'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- User Management List -->
        <div class="lg:col-span-2 bg-slate-900 rounded-lg border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 flex justify-between items-center">
                <h3 class="text-base font-bold text-slate-100">Your team</h3>
                <div class="relative">
                    <input type="text" placeholder="Search staff..." class="bg-slate-950 border border-slate-700 text-sm rounded-lg px-3 py-1.5 w-48 text-slate-200 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-xs font-semibold text-slate-500 border-b border-slate-800">
                            <th class="px-6 py-3">Team member</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($recent_users as $staff)
                        <tr class="hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="h-9 w-9 rounded-lg bg-slate-800 flex items-center justify-center text-slate-300 font-bold mr-3 text-xs">
                                        {{ substr($staff->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-slate-100">{{ $staff->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $staff->username }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($staff->roles as $role)
                                        <span class="px-2 py-1 text-xs font-medium bg-blue-950 text-blue-400 border border-blue-800 rounded-md">{{ $role->slug }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full
                                    @if($staff->status == 'active') bg-emerald-950 text-emerald-400 border border-emerald-800
                                    @elseif($staff->status == 'locked') bg-rose-950 text-rose-400 border border-rose-800
                                    @else bg-amber-950 text-amber-400 border border-amber-800 @endif">
                                    {{ ucfirst($staff->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-slate-500 hover:text-blue-400 transition-colors" title="Edit">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 bg-slate-950/50 border-t border-slate-800">
                <a href="{{ route('admin.staff.index') }}" class="text-sm font-semibold text-emerald-400 hover:text-emerald-300 transition-colors">See everyone on the team &rarr;</a>
            </div>
        </div>

        <!-- Security Feed -->
        <div class="space-y-6">
            <div class="bg-slate-900 p-6 rounded-lg border border-slate-800">
                <h3 class="text-sm font-semibold text-slate-400 mb-4">Quick actions</h3>
                <a href="{{ route('admin.patients.merge') }}" class="flex items-center justify-between p-3 bg-slate-950 rounded-lg border border-slate-800 hover:border-emerald-700 transition-all group">
                    <span class="text-sm font-semibold text-slate-300">Merge duplicate patients</span>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-slate-500 group-hover:text-emerald-400"></i>
                </a>
            </div>

            <div class="bg-slate-900 rounded-lg border border-slate-800 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-100">Recent activity</h3>
                    <div class="h-2 w-2 bg-emerald-500 rounded-full animate-pulse"></div>
                </div>
                <div class="p-4">
                    <div class="space-y-3">
                        @foreach($recent_logs as $log)
                        <div class="p-3 rounded-lg bg-slate-950 border border-slate-800 hover:border-emerald-800 transition-all duration-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-semibold text-slate-200">{{ $log->user->name ?? 'System' }}</span>
                                <span class="text-xs font-medium text-slate-500">{{ $log->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="text-xs font-medium text-emerald-400 bg-emerald-950/50 inline-block px-2 py-0.5 rounded-md">
                                {{ str_replace('_', ' ', $log->action) }}
                            </div>
                            <p class="text-xs text-slate-500 mt-2 font-medium truncate">{{ $log->ip_address }} &bull; {{ $log->user_agent }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-slate-800 text-center">
                    <button class="text-sm font-semibold text-slate-500 hover:text-emerald-400 transition-colors">View the full activity log</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
