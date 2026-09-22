@extends('layouts.app')

@section('title', 'Admin Management')

@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 tracking-tight">Staff &amp; Settings</h1>
            <p class="text-sm text-slate-500 mt-0.5">Manage who has access and how the facility runs.</p>
        </div>
        <div class="flex gap-2 shrink-0">
            <button type="button" class="bg-white text-slate-700 px-4 py-2 rounded-lg font-medium text-sm border border-slate-300 hover:bg-slate-50 transition-colors">
                Audit logs
            </button>
            <a href="{{ route('admin.staff.create') }}" class="bg-brand-600 text-white px-4 py-2 rounded-lg font-semibold text-sm hover:bg-brand-700 transition-colors flex items-center">
                <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>
                Add team member
            </a>
        </div>
    </div>

    <!-- Admin Quick Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-sm text-slate-500 mb-1 flex items-center"><span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span>Active accounts</p>
            <p class="text-2xl font-semibold text-slate-900">{{ $stats['active_users'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-sm text-slate-500 mb-1 flex items-center"><span class="w-2 h-2 rounded-full bg-red-500 mr-2"></span>Locked out</p>
            <p class="text-2xl font-semibold text-slate-900">{{ $stats['locked_users'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-sm text-slate-500 mb-1 flex items-center"><span class="w-2 h-2 rounded-full bg-amber-500 mr-2"></span>Suspended</p>
            <p class="text-2xl font-semibold text-slate-900">{{ $stats['suspended_users'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-sm text-slate-500 mb-1 flex items-center"><span class="w-2 h-2 rounded-full bg-brand-600 mr-2"></span>Total team members</p>
            <p class="text-2xl font-semibold text-slate-900">{{ $stats['total_users'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- User Management List -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 flex justify-between items-center gap-3">
                <h3 class="text-sm font-semibold text-slate-900">Your team</h3>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-slate-400">
                        <i data-lucide="search" class="w-3.5 h-3.5"></i>
                    </span>
                    <input type="search" placeholder="Search staff"
                           class="bg-white border border-slate-300 text-sm rounded-lg pl-8 pr-3 py-1.5 w-48 text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-xs font-medium text-slate-500 bg-slate-50">
                            <th class="px-5 py-2.5">Team member</th>
                            <th class="px-5 py-2.5">Role</th>
                            <th class="px-5 py-2.5">Status</th>
                            <th class="px-5 py-2.5"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($recent_users as $staff)
                        <tr class="hover:bg-brand-50/40 transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center">
                                    <div class="h-9 w-9 rounded-lg bg-brand-50 flex items-center justify-center text-brand-700 text-sm font-semibold mr-3 shrink-0">
                                        {{ strtoupper(substr($staff->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-slate-900 truncate">{{ $staff->name }}</div>
                                        <div class="text-xs text-slate-500 truncate">{{ $staff->username }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($staff->roles as $role)
                                        <span class="px-2 py-0.5 text-xs font-medium bg-brand-50 text-brand-700 rounded-md">{{ ucfirst(str_replace('_', ' ', $role->slug)) }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full
                                    @if($staff->status == 'active') bg-green-50 text-green-700
                                    @elseif($staff->status == 'locked') bg-red-50 text-red-700
                                    @else bg-amber-50 text-amber-700 @endif">
                                    {{ ucfirst($staff->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <button type="button" class="p-1.5 rounded-lg text-slate-400 hover:text-brand-600 hover:bg-brand-50 transition-colors" title="Edit" aria-label="Edit {{ $staff->name }}">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-slate-200">
                <a href="{{ route('admin.staff.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700 transition-colors">See everyone on the team &rarr;</a>
            </div>
        </div>

        <!-- Security Feed -->
        <div class="space-y-5">
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900 mb-3">Quick actions</h3>
                <a href="{{ route('admin.patients.merge') }}" class="flex items-center justify-between p-3 bg-slate-50 rounded-lg hover:bg-brand-50 transition-colors group">
                    <span class="text-sm font-medium text-slate-700 group-hover:text-brand-700">Merge duplicate patients</span>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 group-hover:text-brand-600"></i>
                </a>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900">Recent activity</h3>
                    <span class="h-2 w-2 bg-brand-500 rounded-full animate-pulse"></span>
                </div>
                <div class="p-3 space-y-2">
                    @foreach($recent_logs as $log)
                    <div class="p-3 rounded-lg bg-slate-50">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <span class="text-sm font-medium text-slate-900 truncate">{{ $log->user->name ?? 'System' }}</span>
                            <span class="text-xs text-slate-400 shrink-0">{{ $log->created_at->diffForHumans() }}</span>
                        </div>
                        <span class="inline-block text-xs font-medium text-brand-700 bg-brand-50 px-2 py-0.5 rounded-md">
                            {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                        </span>
                        <p class="text-xs text-slate-400 mt-1.5 truncate" title="{{ $log->user_agent }}">{{ $log->ip_address }} &bull; {{ $log->user_agent }}</p>
                    </div>
                    @endforeach
                </div>
                <div class="px-5 py-3 border-t border-slate-200 text-center">
                    <button type="button" class="text-sm font-medium text-brand-600 hover:text-brand-700 transition-colors">View the full activity log</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection