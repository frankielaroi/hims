@extends('layouts.app')

@section('title', 'Change Password')

@section('content')
@php
    $base  = 'block w-full rounded-lg border bg-white pl-3.5 pr-10 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:outline-none focus:ring-2';
    $ok    = 'border-slate-300 focus:border-brand-500 focus:ring-brand-500/20';
    $bad   = 'border-red-400 focus:border-red-500 focus:ring-red-500/20';
    $label = 'mb-1.5 block text-sm font-medium text-slate-700';
    $eye   = 'absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-700 transition-colors';
@endphp

<div class="max-w-md mx-auto py-6">
    <div class="mb-6 text-center">
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50">
            <i data-lucide="lock-keyhole" class="h-6 w-6 text-brand-600"></i>
        </div>
        <h1 class="text-xl font-semibold tracking-tight text-slate-900">Change your password</h1>
        <p class="mt-1 text-sm text-slate-500">Choose a new password you don't use anywhere else.</p>
    </div>

    <form id="passwordForm" action="{{ route('password.update') }}" method="POST"
          class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
        @csrf

        <!-- Current password -->
        <div>
            <label for="current_password" class="{{ $label }}">Current password</label>
            <div class="relative">
                <input id="current_password" name="current_password" type="password" required autofocus autocomplete="current-password"
                       class="{{ $base }} {{ $errors->has('current_password') ? $bad : $ok }}">
                <button type="button" class="{{ $eye }}" data-toggle="current_password" aria-label="Show password">
                    <span class="icon-show"><i data-lucide="eye" class="h-4 w-4"></i></span>
                    <span class="icon-hide hidden"><i data-lucide="eye-off" class="h-4 w-4"></i></span>
                </button>
            </div>
            @error('current_password')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <hr class="border-slate-100">

        <!-- New password -->
        <div>
            <label for="password" class="{{ $label }}">New password</label>
            <div class="relative">
                <input id="password" name="password" type="password" required autocomplete="new-password"
                       class="{{ $base }} {{ $errors->has('password') ? $bad : $ok }}">
                <button type="button" class="{{ $eye }}" data-toggle="password" aria-label="Show password">
                    <span class="icon-show"><i data-lucide="eye" class="h-4 w-4"></i></span>
                    <span class="icon-hide hidden"><i data-lucide="eye-off" class="h-4 w-4"></i></span>
                </button>
            </div>
            @error('password')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror

            <!-- Strength meter -->
            <div class="mt-3">
                <div class="flex gap-1.5" aria-hidden="true">
                    <span class="strength-seg h-1.5 flex-1 rounded-full bg-slate-200 transition-colors"></span>
                    <span class="strength-seg h-1.5 flex-1 rounded-full bg-slate-200 transition-colors"></span>
                    <span class="strength-seg h-1.5 flex-1 rounded-full bg-slate-200 transition-colors"></span>
                    <span class="strength-seg h-1.5 flex-1 rounded-full bg-slate-200 transition-colors"></span>
                </div>
                <p id="strengthLabel" class="mt-1.5 text-xs text-slate-500" aria-live="polite">Strength: enter a password</p>
            </div>

            <!-- Requirements -->
            <ul class="mt-3 space-y-1.5 text-xs text-slate-500">
                <li class="req flex items-center gap-2" data-rule="length"><span class="req-dot h-1.5 w-1.5 rounded-full bg-slate-300"></span><span>At least 8 characters</span></li>
                <li class="req flex items-center gap-2" data-rule="case"><span class="req-dot h-1.5 w-1.5 rounded-full bg-slate-300"></span><span>Upper and lower case letters</span></li>
                <li class="req flex items-center gap-2" data-rule="number"><span class="req-dot h-1.5 w-1.5 rounded-full bg-slate-300"></span><span>At least one number</span></li>
                <li class="req flex items-center gap-2" data-rule="different"><span class="req-dot h-1.5 w-1.5 rounded-full bg-slate-300"></span><span>Different from your current password</span></li>
            </ul>
        </div>

        <!-- Confirm -->
        <div>
            <label for="password_confirmation" class="{{ $label }}">Confirm new password</label>
            <div class="relative">
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                       class="{{ $base }} {{ $ok }}">
                <button type="button" class="{{ $eye }}" data-toggle="password_confirmation" aria-label="Show password">
                    <span class="icon-show"><i data-lucide="eye" class="h-4 w-4"></i></span>
                    <span class="icon-hide hidden"><i data-lucide="eye-off" class="h-4 w-4"></i></span>
                </button>
            </div>
            <p id="matchMessage" class="mt-1.5 hidden text-xs" aria-live="polite"></p>
        </div>

        <button id="submitBtn" type="submit" disabled
                class="flex w-full justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-brand-600">
            Update password
        </button>
    </form>

    <p class="mt-4 text-center text-xs text-slate-400">You'll stay signed in on this device after changing your password.</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const MIN_LENGTH = 8; // keep in step with the password rule in your controller

    const current = document.getElementById('current_password');
    const pass = document.getElementById('password');
    const confirmInput = document.getElementById('password_confirmation');
    const submitBtn = document.getElementById('submitBtn');
    const matchMessage = document.getElementById('matchMessage');
    const strengthLabel = document.getElementById('strengthLabel');
    const segments = document.querySelectorAll('.strength-seg');

    const levels = [
        { label: 'Too weak', color: 'bg-red-500' },
        { label: 'Weak', color: 'bg-red-500' },
        { label: 'Fair', color: 'bg-amber-500' },
        { label: 'Good', color: 'bg-brand-500' },
        { label: 'Strong', color: 'bg-green-500' }
    ];
    const segColors = ['bg-red-500', 'bg-amber-500', 'bg-brand-500', 'bg-green-500'];

    // Show / hide password
    document.querySelectorAll('[data-toggle]').forEach(btn => {
        btn.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.toggle);
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            this.querySelector('.icon-show').classList.toggle('hidden', !showing);
            this.querySelector('.icon-hide').classList.toggle('hidden', showing);
            this.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    });

    function setRule(rule, met) {
        const item = document.querySelector(`.req[data-rule="${rule}"]`);
        const dot = item.querySelector('.req-dot');
        item.classList.toggle('text-green-700', met);
        item.classList.toggle('text-slate-500', !met);
        dot.classList.toggle('bg-green-500', met);
        dot.classList.toggle('bg-slate-300', !met);
    }

    function update() {
        const value = pass.value;

        const rules = {
            length: value.length >= MIN_LENGTH,
            case: /[a-z]/.test(value) && /[A-Z]/.test(value),
            number: /\d/.test(value),
            different: value.length > 0 && value !== current.value
        };
        Object.keys(rules).forEach(rule => setRule(rule, rules[rule]));

        // Strength: length, mixed case, number, symbol
        const hasSymbol = /[^A-Za-z0-9]/.test(value);
        const score = value.length === 0 ? 0 : [rules.length, rules.case, rules.number, hasSymbol].filter(Boolean).length;

        segments.forEach((seg, i) => {
            segColors.forEach(c => seg.classList.remove(c));
            seg.classList.remove('bg-slate-200');
            if (i < score) {
                seg.classList.add(segColors[Math.max(score - 1, 0)]);
            } else {
                seg.classList.add('bg-slate-200');
            }
        });
        strengthLabel.textContent = value.length === 0 ? 'Strength: enter a password' : 'Strength: ' + levels[score].label;

        // Match
        const confirmValue = confirmInput.value;
        const matches = confirmValue.length > 0 && confirmValue === value;
        if (confirmValue.length === 0) {
            matchMessage.classList.add('hidden');
        } else {
            matchMessage.classList.remove('hidden');
            matchMessage.textContent = matches ? 'Passwords match' : "Passwords don't match yet";
            matchMessage.classList.toggle('text-green-700', matches);
            matchMessage.classList.toggle('text-red-600', !matches);
        }

        submitBtn.disabled = !(current.value.length > 0 && rules.length && rules.case && rules.number && rules.different && matches);
    }

    [current, pass, confirmInput].forEach(el => el.addEventListener('input', update));
    update();
});
</script>
@endsection
