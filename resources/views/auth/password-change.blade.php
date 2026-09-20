@extends('layouts.app')

@section('title', 'Update Your Password')

@section('content')
<div class="min-h-[70vh] flex items-center justify-center">
    <div class="bg-slate-900 p-8 sm:p-10 rounded-2xl shadow-xl border border-slate-800 w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-emerald-950 rounded-2xl text-emerald-400 mb-5 border border-emerald-800">
                <i data-lucide="lock" class="w-7 h-7"></i>
            </div>
            <h2 class="text-2xl font-bold text-slate-100">Let's set a new password</h2>
            <p class="text-slate-400 mt-2 text-sm">For your security, please choose a new password before continuing.</p>
        </div>
        <form action="{{ route('password.update') }}" method="POST" class="space-y-5">
            @csrf
            <div>
                <label for="password" class="block text-sm font-semibold text-slate-300 mb-2">New password</label>
                <input type="password" name="password" id="password" class="w-full px-4 py-3.5 border border-slate-700 bg-slate-950 rounded-xl text-slate-100 font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all" required autocomplete="new-password">
                <p class="text-xs text-slate-500 mt-2">Use at least 8 characters, mixing letters and numbers.</p>
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-slate-300 mb-2">Confirm new password</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="w-full px-4 py-3.5 border border-slate-700 bg-slate-950 rounded-xl text-slate-100 font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all" required autocomplete="new-password">
            </div>
            <button type="submit" class="w-full py-3.5 bg-emerald-600 text-white rounded-xl font-semibold hover:bg-emerald-500 shadow-lg shadow-emerald-950/50 transition-all duration-200">
                Update password
            </button>
        </form>
    </div>
</div>
@endsection
