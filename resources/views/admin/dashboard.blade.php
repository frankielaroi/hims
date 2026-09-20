@extends('layouts.app')

@section('title', 'Admin Management')

@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Staff & Settings</h1>
            <p class="text-slate-500 mt-1">Manage who has access and how the facility runs.</p>
        </div>
        <div class="flex space-x-4">
            <a href="{{ route('admin.staff.create') }}" class="bg-blue-600 text-white px-6 py-3 rounded-2xl font-semibold hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Add team member
            </a>
            <button class="bg-white text-slate-700 px-6 py-3 rounded-2xl font-semibold border border-slate-200 hover:bg-slate-50 transition-all duration-200">
                Audit logs
            </button>
        </div>
    </div>

    <!-- Admin Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
            <p class="text-sm font-medium text-slate-500 mb-1">Active accounts</p>
            <p class="text-3xl font-bold text-green-600">{{ $stats['active_users'] }}</p>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
            <p class="text-sm font-medium text-slate-500 mb-1">Locked out</p>
            <p class="text-3xl font-bold text-red-500">{{ $stats['locked_users'] }}</p>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
            <p class="text-sm font-medium text-slate-500 mb-1">Suspended</p>
            <p class="text-3xl font-bold text-orange-500">{{ $stats['suspended_users'] }}</p>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
            <p class="text-sm font-medium text-slate-500 mb-1">Total team members</p>
            <p class="text-3xl font-bold text-slate-900">{{ $stats['total_users'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- User Management List -->
        <div class="lg:col-span-2 bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900">Your team</h3>
                <div class="relative">
                    <input type="text" placeholder="Search staff..." class="bg-slate-50 border-none text-sm rounded-xl px-4 py-2 w-48 focus:ring-blue-500">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-xs font-semibold text-slate-400 border-b border-slate-50">
                            <th class="px-8 py-4">Team member</th>
                            <th class="px-8 py-4">Role</th>
                            <th class="px-8 py-4">Status</th>
                            <th class="px-8 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($recent_users as $staff)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-8 py-5">
                                <div class="flex items-center">
                                    <div class="h-9 w-9 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 font-bold mr-3 text-xs">
                                        {{ substr($staff->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-slate-900">{{ $staff->name }}</div>
                                        <div class="text-xs text-slate-400">{{ $staff->username }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-5">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($staff->roles as $role)
                                        <span class="px-2 py-1 text-xs font-medium bg-blue-50 text-blue-700 rounded-md">{{ $role->slug }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-8 py-5">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full
                                    @if($staff->status == 'active') bg-green-50 text-green-700
                                    @elseif($staff->status == 'locked') bg-red-50 text-red-700
                                    @else bg-orange-50 text-orange-700 @endif">
                                    {{ ucfirst($staff->status) }}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <button class="text-slate-400 hover:text-blue-600 transition-colors" title="Edit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100">
                <a href="{{ route('admin.staff.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700 transition-colors">See everyone on the team &rarr;</a>
            </div>
        </div>

        <!-- Security Feed -->
        <div class="space-y-8">
            <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200">
                <h3 class="text-sm font-semibold text-slate-500 mb-6">Quick actions</h3>
                <a href="{{ route('admin.patients.merge') }}" class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100 hover:border-blue-200 transition-all group">
                    <span class="text-sm font-semibold text-slate-700">Merge duplicate patients</span>
                    <svg class="w-4 h-4 text-slate-400 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
            </div>

            <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-900">Recent activity</h3>
                <div class="h-2 w-2 bg-blue-500 rounded-full"></div>
            </div>
            <div class="p-4">
                <div class="space-y-4">
                    @foreach($recent_logs as $log)
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 hover:border-blue-100 transition-all duration-200">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-semibold text-slate-900">{{ $log->user->name ?? 'System' }}</span>
                            <span class="text-xs font-medium text-slate-400">{{ $log->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="text-xs font-medium text-blue-600 bg-blue-50/50 inline-block px-2 py-0.5 rounded-md">
                            {{ str_replace('_', ' ', $log->action) }}
                        </div>
                        <p class="text-xs text-slate-400 mt-2 font-medium truncate">{{ $log->ip_address }} &bull; {{ $log->user_agent }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="px-8 py-6 border-t border-slate-100 text-center">
                <button class="text-sm font-semibold text-slate-500 hover:text-blue-600 transition-colors">View the full activity log</button>
            </div>
        </div>
    </div>
</div>
@endsection
