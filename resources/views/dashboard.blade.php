@extends('layouts.app')

@section('title', 'Healthcare Insights')

@section('content')
<div class="space-y-6">
    <!-- Header with Search -->
    <div class="flex items-center justify-between gap-4">
        <div class="flex-1 max-w-lg">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </span>
                <input type="text" placeholder="Search patients, staff..."
                       class="block w-full pl-10 pr-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
            </div>
        </div>
        <button class="p-2 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-white transition-colors" title="Notifications" aria-label="Notifications">
            <i data-lucide="bell" class="w-5 h-5"></i>
        </button>
    </div>

    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', $user->name)[0] }}</h1>
            <p class="text-slate-500 text-sm mt-1">Here's how City Medical Center is doing today.</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-lg px-4 py-2 text-sm font-medium text-slate-700 flex items-center shadow-sm">
            Last 7 days
            <i data-lucide="chevron-down" class="w-4 h-4 ml-2 text-slate-400"></i>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-sm text-slate-500 mb-1">Active admissions</p>
            <div class="flex items-baseline space-x-2">
                <p class="text-2xl font-semibold text-slate-900">784</p>
                <span class="text-xs font-medium text-brand-600">+3.1%</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-sm text-slate-500 mb-1">Patients seen today</p>
            <div class="flex items-baseline space-x-2">
                <p class="text-2xl font-semibold text-slate-900">1,245</p>
                <span class="text-xs font-medium text-brand-600">+5.7%</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-sm text-slate-500 mb-1">Beds occupied</p>
            <div class="flex items-baseline space-x-2">
                <p class="text-2xl font-semibold text-slate-900">89%</p>
                <span class="text-xs font-medium text-red-600">Running high</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-sm text-slate-500 mb-1">Average wait time</p>
            <div class="flex items-baseline space-x-2">
                <p class="text-2xl font-semibold text-slate-900">12 min</p>
                <span class="text-xs font-medium text-brand-600">Good</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Admission Trends Chart -->
        <div class="lg:col-span-2 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-base font-semibold text-slate-900">Admissions this week</h3>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <span class="w-2.5 h-2.5 bg-brand-600 rounded-full mr-2"></span>
                        <span class="text-xs text-slate-500">Admissions</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-2.5 h-2.5 bg-sky-300 rounded-full mr-2"></span>
                        <span class="text-xs text-slate-500">Discharges</span>
                    </div>
                </div>
            </div>
            <div class="h-64">
                <canvas id="admissionChart"></canvas>
            </div>
        </div>

        <!-- Department Activity -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900 mb-6">Busiest departments</h3>
            <div class="space-y-4">
                @php
                    $depts = [
                        ['name' => 'Emergency', 'val' => 94, 'color' => 'bg-red-500'],
                        ['name' => 'Surgery', 'val' => 28, 'color' => 'bg-brand-600'],
                        ['name' => 'Pediatrics', 'val' => 65, 'color' => 'bg-brand-600'],
                        ['name' => 'OPD', 'val' => 115, 'color' => 'bg-brand-600'],
                        ['name' => 'Other', 'val' => 13, 'color' => 'bg-brand-600'],
                    ];
                @endphp
                @foreach($depts as $dept)
                <div>
                    <div class="flex justify-between text-xs text-slate-500 mb-1.5">
                        <span class="font-medium text-slate-700">{{ $dept['name'] }}</span>
                        <span>{{ $dept['val'] }}</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                        <div class="{{ $dept['color'] }} h-full rounded-full" style="width: {{ ($dept['val'] / 120) * 100 }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Staff Availability -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900 mb-4">Who's on today</h3>
            <div class="flex items-center justify-between">
                <div class="w-1/2">
                    <canvas id="staffChart"></canvas>
                </div>
                <div class="w-1/2 pl-6 space-y-4">
                    <div class="flex items-center">
                        <span class="w-2.5 h-2.5 bg-brand-700 rounded-full mr-2.5"></span>
                        <div>
                            <p class="text-sm font-medium text-slate-900">Doctors</p>
                            <p class="text-xs text-slate-500">112 on duty</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="w-2.5 h-2.5 bg-brand-500 rounded-full mr-2.5"></span>
                        <div>
                            <p class="text-sm font-medium text-slate-900">Nurses</p>
                            <p class="text-xs text-slate-500">218 on duty</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="w-2.5 h-2.5 bg-sky-300 rounded-full mr-2.5"></span>
                        <div>
                            <p class="text-sm font-medium text-slate-900">Admin staff</p>
                            <p class="text-xs text-slate-500">45 on duty</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Discharges -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900 mb-6">Ready to go home</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-brand-50 rounded-lg flex items-center justify-center text-brand-600 mr-3">
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-900">Awaiting sign-off</p>
                            <p class="text-xs text-slate-500">Discharges</p>
                        </div>
                    </div>
                    <span class="text-xs text-slate-400">Just now</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-brand-50 rounded-lg flex items-center justify-center text-brand-600 mr-3">
                            <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-900">Cleared from surgery</p>
                            <p class="text-xs text-slate-500">Discharges</p>
                        </div>
                    </div>
                    <span class="text-xs text-slate-400">1 hour ago</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-brand-50 rounded-lg flex items-center justify-center text-brand-600 mr-3">
                            <i data-lucide="bed" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-900">New today</p>
                            <p class="text-xs text-slate-500">Discharges</p>
                        </div>
                    </div>
                    <span class="text-xs text-slate-400">3 hours ago</span>
                </div>
            </div>
        </div>

        <!-- Critical Notifications -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900 mb-6">Needs your attention</h3>
            <div class="space-y-3">
                <div class="p-4 bg-red-50 rounded-lg border border-red-100 flex items-start">
                    <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center text-red-500 mr-3 shrink-0">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                    </div>
                    <p class="text-sm font-medium text-red-800 leading-snug">A few drugs are running low in stock</p>
                </div>
                <div class="p-4 bg-red-50 rounded-lg border border-red-100 flex items-start">
                    <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center text-red-500 mr-3 shrink-0">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                    </div>
                    <p class="text-sm font-medium text-red-800 leading-snug">Some equipment needs servicing soon</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.font.family = 'Inter, sans-serif';

    // Admission Trends
    const admCtx = document.getElementById('admissionChart').getContext('2d');
    new Chart(admCtx, {
        type: 'line',
        data: {
            labels: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            datasets: [{
                label: 'Admissions',
                data: [100, 115, 105, 120, 110, 125, 115],
                borderColor: '#2563EB',
                backgroundColor: 'rgba(37, 99, 235, 0.08)',
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 0
            }, {
                label: 'Discharges',
                data: [90, 120, 100, 110, 105, 115, 130],
                borderColor: '#7DD3FC',
                backgroundColor: 'rgba(125, 211, 252, 0.10)',
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { min: 80, max: 140, grid: { color: '#F1F5F9' }, ticks: { color: '#94A3B8' }, border: { display: false } },
                x: { grid: { display: false }, ticks: { color: '#94A3B8' }, border: { display: false } }
            }
        }
    });

    // Staff Availability
    const staffCtx = document.getElementById('staffChart').getContext('2d');
    new Chart(staffCtx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [112, 218, 45],
                backgroundColor: ['#1D4ED8', '#3B82F6', '#7DD3FC'],
                borderWidth: 0,
                cutout: '72%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });
});
</script>
@endpush