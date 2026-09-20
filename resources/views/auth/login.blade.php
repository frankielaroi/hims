@extends('layouts.app')

@section('title', 'Sign In')

@section('content')
<div class="min-h-[80vh] flex flex-col items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-800 text-white text-3xl border border-emerald-500/30 shadow-lg shadow-emerald-950/50 mb-6">
                🇬🇭
            </div>
            <h2 class="text-3xl font-bold text-slate-100 tracking-tight">Welcome back</h2>
            <p class="mt-2 text-sm text-slate-400">Sign in to City Medical Center's patient system</p>
        </div>

        <div class="bg-slate-900 p-8 sm:p-10 rounded-2xl shadow-xl border border-slate-800">
            @if(config('app.env') === 'local')
            <div class="bg-emerald-950 rounded-lg p-4 mb-6 border border-emerald-800">
                <div class="flex">
                    <i data-lucide="info" class="h-5 w-5 text-emerald-400 shrink-0"></i>
                    <div class="ml-3">
                        <p class="text-xs font-semibold text-emerald-300">Local development sign-in</p>
                        <p class="mt-1 text-xs text-emerald-400">Username: <span class="font-semibold">admin.user</span> &middot; Password: <span class="font-semibold">password123</span></p>
                    </div>
                </div>
            </div>
            @endif

            <form class="space-y-5" action="{{ route('login') }}" method="POST">
                @csrf
                <div>
                    <label for="username" class="block text-sm font-semibold text-slate-300 mb-2">Username</label>
                    <input id="username" name="username" type="text" required class="block w-full px-4 py-3.5 border border-slate-700 bg-slate-950 rounded-xl text-slate-100 font-medium placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all" placeholder="e.g. j.doe" autofocus autocomplete="username">
                </div>
                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-300 mb-2">Password</label>
                    <input id="password" name="password" type="password" required class="block w-full px-4 py-3.5 border border-slate-700 bg-slate-950 rounded-xl text-slate-100 font-medium placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all" placeholder="Enter your password" autocomplete="current-password">
                </div>

                <button type="submit" class="w-full flex justify-center py-3.5 px-4 rounded-xl font-semibold text-white bg-emerald-600 hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-900 focus:ring-emerald-500 shadow-lg shadow-emerald-950/50 transition-all duration-200">
                    Sign in
                </button>

                <p class="text-center text-xs text-slate-500 pt-1">
                    Forgotten your password? Ask an administrator to reset it for you.
                </p>
            </form>
        </div>

        <p class="text-center text-xs text-slate-600">
            &copy; {{ date('Y') }} City Medical Center &middot; Staff access only
        </p>
    </div>
</div>
@endsection
