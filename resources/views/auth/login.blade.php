@extends('layouts.app')

@section('title', 'Sign In')

@section('content')
<div class="min-h-[80vh] flex flex-col items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <div class="inline-flex items-center justify-center p-4 bg-teal-600 rounded-[2rem] shadow-xl shadow-teal-100 mb-6">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <h2 class="text-3xl font-bold text-slate-900 tracking-tight">Welcome back</h2>
            <p class="mt-2 text-sm text-slate-500">Sign in to City Medical Center's patient system</p>
        </div>

        <div class="bg-white p-8 sm:p-10 rounded-[2.5rem] shadow-xl shadow-slate-100 border border-slate-100">
            @if(config('app.env') === 'local')
            <div class="bg-teal-50 rounded-2xl p-4 mb-6 border border-teal-100">
                <div class="flex">
                    <svg class="h-5 w-5 text-teal-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-3">
                        <p class="text-xs font-semibold text-teal-800">Local development sign-in</p>
                        <p class="mt-1 text-xs text-teal-700">Username: <span class="font-semibold">admin.user</span> &middot; Password: <span class="font-semibold">password123</span></p>
                    </div>
                </div>
            </div>
            @endif

            <form class="space-y-5" action="{{ route('login') }}" method="POST">
                @csrf
                <div>
                    <label for="username" class="block text-sm font-semibold text-slate-700 mb-2">Username</label>
                    <input id="username" name="username" type="text" required class="block w-full px-4 py-3.5 border border-slate-200 bg-slate-50 rounded-xl text-slate-900 font-medium placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all" placeholder="e.g. j.doe" autofocus autocomplete="username">
                </div>
                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-700 mb-2">Password</label>
                    <input id="password" name="password" type="password" required class="block w-full px-4 py-3.5 border border-slate-200 bg-slate-50 rounded-xl text-slate-900 font-medium placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all" placeholder="Enter your password" autocomplete="current-password">
                </div>

                <button type="submit" class="w-full flex justify-center py-3.5 px-4 rounded-xl font-semibold text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 shadow-lg shadow-teal-100 transition-all duration-200">
                    Sign in
                </button>

                <p class="text-center text-xs text-slate-400 pt-1">
                    Forgotten your password? Ask an administrator to reset it for you.
                </p>
            </form>
        </div>

        <p class="text-center text-xs text-slate-400">
            &copy; {{ date('Y') }} City Medical Center &middot; Staff access only
        </p>
    </div>
</div>
@endsection
