@extends('layouts.app')

@section('title', 'Staff Management')

@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Team</h1>
            <p class="text-slate-500 mt-1">Everyone with access to the system, medical and administrative.</p>
        </div>
        <a href="{{ route('admin.staff.create') }}" class="bg-blue-600 text-white px-6 py-3 rounded-2xl font-semibold hover:bg-blue-700 shadow-lg shadow-blue-100 transition-all duration-200 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add team member
        </a>
    </div>

    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-semibold text-slate-400 border-b border-slate-50">
                        <th class="px-8 py-4">Team member</th>
                        <th class="px-8 py-4">Roles</th>
                        <th class="px-8 py-4">Status</th>
                        <th class="px-8 py-4">Joined</th>
                        <th class="px-8 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($staff as $member)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-8 py-5">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 font-bold mr-4">
                                    {{ substr($member->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $member->name }}</div>
                                    <div class="text-xs text-slate-400">{{ $member->username }} @if($member->email) &bull; {{ $member->email }} @endif</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-5">
                            <div class="flex flex-wrap gap-1">
                                @foreach($member->roles as $role)
                                    <span class="px-2 py-1 text-xs font-medium bg-blue-50 text-blue-700 rounded-md">{{ $role->slug }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-8 py-5">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full
                                @if($member->status == 'active') bg-green-50 text-green-700
                                @elseif($member->status == 'locked') bg-red-50 text-red-700
                                @else bg-orange-50 text-orange-700 @endif">
                                {{ ucfirst($member->status) }}
                            </span>
                        </td>
                        <td class="px-8 py-5">
                            <div class="text-xs font-medium text-slate-500">{{ $member->created_at->format('d M, Y') }}</div>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <button class="text-slate-400 hover:text-blue-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($staff->hasPages())
        <div class="px-8 py-6 bg-slate-50 border-t border-slate-100">
            {{ $staff->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
