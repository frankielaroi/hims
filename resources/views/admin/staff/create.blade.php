@extends('layouts.app')

@section('title', 'Add Staff')

@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Add a team member</h1>
            <p class="text-slate-500 mt-1">Create their account and choose what they'll have access to.</p>
        </div>
        <a href="{{ route('admin.staff.index') }}" class="text-slate-500 hover:text-slate-700 font-semibold text-sm flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to team
        </a>
    </div>

    <form action="{{ route('admin.staff.store') }}" method="POST" class="space-y-8">
        @csrf

        <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-slate-200">
            <h3 class="text-sm font-semibold text-slate-500 mb-6">Their details</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Full name</label>
                    <input type="text" name="name" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-blue-500" placeholder="e.g. Dr. Kwame Mensah">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Username</label>
                    <input type="text" name="username" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-blue-500" placeholder="e.g. k.mensah">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Email (optional)</label>
                    <input type="email" name="email" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-blue-500" placeholder="e.g. doctor@hims.gh">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Temporary password</label>
                    <input type="password" name="password" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-blue-500" placeholder="••••••••">
                    <p class="text-xs text-slate-400 mt-2">They'll be asked to set their own password on first sign-in.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Status</label>
                    <select name="status" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-blue-500">
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="disabled">Disabled</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-slate-200">
            <h3 class="text-sm font-semibold text-slate-500 mb-6">What can they do?</h3>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                @foreach($roles as $role)
                <label class="relative flex flex-col p-4 bg-slate-50 rounded-2xl border-2 border-transparent cursor-pointer hover:bg-slate-100 transition-all has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500">
                    <input type="checkbox" name="roles[]" value="{{ $role->id }}" class="sr-only">
                    <span class="text-sm font-semibold text-slate-900">{{ $role->name }}</span>
                    <span class="text-xs text-slate-400 mt-1 leading-tight">{{ $role->description }}</span>
                </label>
                @endforeach
            </div>
        </div>

        <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-slate-200">
            <h3 class="text-sm font-semibold text-slate-500 mb-6">Anything else?</h3>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Contact or department notes</label>
                <textarea name="contact_info" rows="2" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-blue-500" placeholder="e.g. Night shift, Emergency Ward"></textarea>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-4 pb-12">
            <button type="submit" class="bg-blue-600 text-white px-12 py-4 rounded-2xl font-semibold hover:bg-blue-700 shadow-lg shadow-blue-100 transition-all">
                Create account
            </button>
        </div>
    </form>
</div>
@endsection
