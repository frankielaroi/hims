@extends('layouts.app')

@section('title', 'Staff Management')

@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 tracking-tight">Team</h1>
            <p class="text-sm text-slate-500 mt-0.5">Everyone with access to the system, medical and administrative.</p>
        </div>
        <a href="{{ route('admin.staff.create') }}" class="bg-brand-600 text-white px-4 py-2 rounded-lg font-semibold text-sm hover:bg-brand-700 transition-colors flex items-center shrink-0">
            <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>
            Add team member
        </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-medium text-slate-500 bg-slate-50">
                        <th class="px-5 py-2.5">Team member</th>
                        <th class="px-5 py-2.5">Roles</th>
                        <th class="px-5 py-2.5">Status</th>
                        <th class="px-5 py-2.5">Joined</th>
                        <th class="px-5 py-2.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($staff as $member)
                    <tr class="hover:bg-brand-50/40 transition-colors">
                        <td class="px-5 py-3">
                            <div class="flex items-center">
                                <div class="h-9 w-9 rounded-lg bg-brand-50 flex items-center justify-center text-brand-700 text-sm font-semibold mr-3 shrink-0">
                                    {{ strtoupper(substr($member->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-slate-900 truncate">{{ $member->name }}</div>
                                    <div class="text-xs text-slate-500 truncate">{{ $member->username }} @if($member->email) &bull; {{ $member->email }} @endif</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach($member->roles as $role)
                                    <span class="px-2 py-0.5 text-xs font-medium bg-brand-50 text-brand-700 rounded-md">{{ ucfirst(str_replace('_', ' ', $role->slug)) }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full
                                @if($member->status == 'active') bg-green-50 text-green-700
                                @elseif($member->status == 'locked') bg-red-50 text-red-700
                                @else bg-amber-50 text-amber-700 @endif">
                                {{ ucfirst($member->status) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            <div class="text-sm text-slate-600">{{ $member->created_at->format('d M, Y') }}</div>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <button type="button" class="p-1.5 rounded-lg text-slate-400 hover:text-brand-600 hover:bg-brand-50 transition-colors" title="Edit" aria-label="Edit {{ $member->name }}">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($staff->hasPages())
        <div class="px-5 py-3 border-t border-slate-200">
            {{ $staff->links() }}
        </div>
        @endif
    </div>
</div>
@endsection