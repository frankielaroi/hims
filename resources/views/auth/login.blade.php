@extends('layouts.app')

@section('title', 'Sign In')

@section('content')
<div class="min-h-[75vh] flex items-center justify-center">
    <div class="w-full max-w-sm">
        <div class="mb-2 text-center">
            
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Welcome back</h1>
            <p class="mt-1 text-sm text-slate-500">Sign in to City Medical Center's patient system</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            @if(config('app.env') === 'local')
                <div class="mb-6 flex gap-3 rounded-lg border border-brand-100 bg-brand-50 p-3.5">
                    <i data-lucide="info" class="mt-0.5 h-4 w-4 shrink-0 text-brand-600"></i>
                    <div class="text-xs text-brand-900">
                        <p class="font-semibold">Local development sign-in</p>
                        <p class="mt-0.5 text-brand-700">
                            Username: <span class="font-semibold">admin.user</span> &middot;
                            Password: <span class="font-semibold">password123</span>
                        </p>
                    </div>
                </div>
            @endif

            <form class="space-y-5" action="{{ route('login') }}" method="POST">
                @csrf

                <div>
                    <label for="username" class="mb-1.5 block text-sm font-medium text-slate-700">Username</label>
                    <input id="username" name="username" type="text" required autofocus autocomplete="username"
                           value="{{ old('username') }}" placeholder="e.g. j.doe"
                           class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           placeholder="Enter your password"
                           class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                </div>

                <button type="submit"
                        class="flex w-full justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                    Sign in
                </button>

                <p class="text-center text-xs text-slate-500">
                    Forgotten your password? Ask an administrator to reset it for you.
                </p>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-slate-400">
            &copy; {{ date('Y') }} City Medical Center &middot; Staff access only
        </p>
    </div>
</div>
@endsection