@extends('layouts.app')

@section('title', 'Update Your Password')

@section('content')
<div class="min-h-[70vh] flex items-center justify-center">
    <div class="bg-white p-8 sm:p-10 rounded-[2.5rem] shadow-xl shadow-slate-100 border border-slate-100 w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-teal-50 rounded-2xl text-teal-600 mb-5">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            <h2 class="text-2xl font-bold text-slate-900">Let's set a new password</h2>
            <p class="text-slate-500 mt-2 text-sm">For your security, please choose a new password before continuing.</p>
        </div>
        <form action="{{ route('password.update') }}" method="POST" class="space-y-5">
            @csrf
            <div>
                <label for="password" class="block text-sm font-semibold text-slate-700 mb-2">New password</label>
                <input type="password" name="password" id="password" class="w-full px-4 py-3.5 border border-slate-200 bg-slate-50 rounded-xl text-slate-900 font-medium focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all" required autocomplete="new-password">
                <p class="text-xs text-slate-400 mt-2">Use at least 8 characters, mixing letters and numbers.</p>
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-slate-700 mb-2">Confirm new password</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="w-full px-4 py-3.5 border border-slate-200 bg-slate-50 rounded-xl text-slate-900 font-medium focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all" required autocomplete="new-password">
            </div>
            <button type="submit" class="w-full py-3.5 bg-teal-600 text-white rounded-xl font-semibold hover:bg-teal-700 shadow-lg shadow-teal-100 transition-all duration-200">
                Update password
            </button>
        </form>
    </div>
</div>
@endsection
