@extends('layouts.app')

@section('title', 'Healthcare Insights')

@section('content')
<div class="space-y-6">
    <!-- Header with Search -->
    <div class="flex items-center justify-between">
        <div class="flex-1 max-w-lg">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </span>
                <input type="text" placeholder="Search patients, staff..." class="block w-full pl-10 pr-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-sm text-slate-200 placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
        </div>
        <button class="text-slate-400 hover:text-slate-100" title="Notifications">
            <i data-lucide="bell" class="w-5 h-5"></i>
        </button>
    </div>

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-100">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', $user->name)[0] }} 👋</h1>
            <p class="text-slate-400 text-sm mt-1">Here's how City Medical Center is doing today.</p>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm font-semibold text-slate-300 flex items-center">
            Last 7 days
            <i data-lucide="chevron-down" class="w-4 h-4 ml-2"></i>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-slate-900 p-5 rounded-lg border border-slate-800">
            <p class="text-sm font-medium text-slate-400 mb-1">Active admissions</p>
            <div class="flex items-baseline space-x-2">
                <p class="text-2xl font-bold text-slate-100">784</p>
                <span class="text-xs font-semibold text-emerald-400">+3.1%</span>
            </div>
        </div>
        <div class="bg-slate-900 p-5 rounded-lg border border-slate-800">
            <p class="text-sm font-medium text-slate-400 mb-1">Patients seen today</p>
            <div class="flex items-baseline space-x-2">
                <p class="text-2xl font-bold text-slate-100">1,245</p>
                <span class="text-xs font-semibold text-emerald-400">+5.7%</span>
            </div>
        </div>
        <div class="bg-slate-900 p-5 rounded-lg border border-slate-800">
            <p class="text-sm font-medium text-slate-400 mb-1">Beds occupied</p>
            <div class="flex items-baseline space-x-2">
                <p class="text-2xl font-bold text-slate-100">89%</p>
                <span class="text-xs font-semibold text-rose-400">Running high</span>
            </div>
        </div>
        <div class="bg-slate-900 p-5 rounded-lg border border-slate-800">
            <p class="text-sm font-medium text-slate-400 mb-1">Average wait time</p>
            <div class="flex items-baseline space-x-2">
                <p class="text-2xl font-bold text-slate-100">12 min</p>
                <span class="text-xs font-semibold text-emerald-400">Good</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Admission Trends Chart -->
        <div class="lg:col-span-2 bg-slate-900 p-6 rounded-lg border border-slate-800">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-base font-bold text-slate-100">Admissions this week</h3>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-blue-400 rounded-full mr-2"></span>
                        <span class="text-xs font-medium text-slate-400">Admissions</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-emerald-400 rounded-full mr-2"></span>
                        <span class="text-xs font-medium text-slate-400">Discharges</span>
                    </div>
                </div>
            </div>
            <div class="h-64">
                <canvas id="admissionChart"></canvas>
            </div>
        </div>

        <!-- Department Activity -->
        <div class="bg-slate-900 p-6 rounded-lg border border-slate-800">
            <h3 class="text-base font-bold text-slate-100 mb-6">Busiest departments</h3>
            <div class="space-y-4">
                @php
                    $depts = [
                        ['name' => 'Emergency', 'val' => 94, 'color' => 'bg-rose-500'],
                        ['name' => 'Surgery', 'val' => 28, 'color' => 'bg-blue-400'],
                        ['name' => 'Pediatrics', 'val' => 65, 'color' => 'bg-emerald-500'],
                        ['name' => 'OPD', 'val' => 115, 'color' => 'bg-blue-300'],
                        ['name' => 'Other', 'val' => 13, 'color' => 'bg-slate-500'],
                    ];
                @endphp
                @foreach($depts as $dept)
                <div>
                    <div class="flex justify-between text-xs font-medium text-slate-400 mb-1">
                        <span>{{ $dept['name'] }}</span>
                        <span>{{ $dept['val'] }}</span>
                    </div>
                    <div class="w-full bg-slate-800 rounded-full h-3 overflow-hidden">
                        <div class="{{ $dept['color'] }} h-full rounded-full" style="width: {{ ($dept['val'] / 120) * 100 }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Staff Availability -->
        <div class="bg-slate-900 p-6 rounded-lg border border-slate-800">
            <h3 class="text-base font-bold text-slate-100 mb-4">Who's on today</h3>
            <div class="flex items-center justify-between">
                <div class="w-1/2">
                    <canvas id="staffChart"></canvas>
                </div>
                <div class="w-1/2 pl-6 space-y-4">
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Doctors</p>
                            <p class="text-xs text-slate-500">112 on duty</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-teal-400 rounded-full mr-2"></span>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Nurses</p>
                            <p class="text-xs text-slate-500">218 on duty</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-emerald-500 rounded-full mr-2"></span>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Admin staff</p>
                            <p class="text-xs text-slate-500">45 on duty</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Discharges -->
        <div class="bg-slate-900 p-6 rounded-lg border border-slate-800">
            <h3 class="text-base font-bold text-slate-100 mb-6">Ready to go home</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-slate-950 rounded-lg border border-slate-800">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-rose-950 border border-rose-800 rounded-lg flex items-center justify-center text-rose-400 mr-3">
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Awaiting sign-off</p>
                            <p class="text-xs text-slate-500">Discharges</p>
                        </div>
                    </div>
                    <span class="text-xs font-medium text-slate-500">Just now</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-slate-950 rounded-lg border border-slate-800">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-teal-950 border border-teal-800 rounded-lg flex items-center justify-center text-teal-400 mr-3">
                            <i data-lucide="tent" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Cleared from surgery</p>
                            <p class="text-xs text-slate-500">Discharges</p>
                        </div>
                    </div>
                    <span class="text-xs font-medium text-slate-500">1 hour ago</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-slate-950 rounded-lg border border-slate-800">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-emerald-950 border border-emerald-800 rounded-lg flex items-center justify-center text-emerald-400 mr-3">
                            <i data-lucide="bed" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">New today</p>
                            <p class="text-xs text-slate-500">Discharges</p>
                        </div>
                    </div>
                    <span class="text-xs font-medium text-slate-500">3 hours ago</span>
                </div>
            </div>
        </div>

        <!-- Critical Notifications -->
        <div class="bg-slate-900 p-6 rounded-lg border border-slate-800">
            <h3 class="text-base font-bold text-slate-100 mb-6">Needs your attention</h3>
            <div class="space-y-3">
                <div class="p-4 bg-rose-950/40 rounded-lg border border-rose-800 flex items-start">
                    <div class="w-8 h-8 bg-rose-950 border border-rose-800 rounded-lg flex items-center justify-center text-rose-400 mr-3 shrink-0">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                    </div>
                    <p class="text-sm font-semibold text-rose-300 leading-snug">A few drugs are running low in stock</p>
                </div>
                <div class="p-4 bg-rose-950/40 rounded-lg border border-rose-800 flex items-start">
                    <div class="w-8 h-8 bg-rose-950 border border-rose-800 rounded-lg flex items-center justify-center text-rose-400 mr-3 shrink-0">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                    </div>
                    <p class="text-sm font-semibold text-rose-300 leading-snug">Some equipment needs servicing soon</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Admission Trends
    const admCtx = document.getElementById('admissionChart').getContext('2d');
    new Chart(admCtx, {
        type: 'line',
        data: {
            labels: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            datasets: [{
                label: 'Admissions',
                data: [100, 115, 105, 120, 110, 125, 115],
                borderColor: '#60A5FA',
                backgroundColor: 'rgba(96, 165, 250, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 0
            }, {
                label: 'Discharges',
                data: [90, 120, 100, 110, 105, 115, 130],
                borderColor: '#34D399',
                backgroundColor: 'rgba(52, 211, 153, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { min: 80, max: 140, grid: { color: '#1e293b' }, ticks: { color: '#64748b' }, border: { display: false } },
                x: { grid: { display: false }, ticks: { color: '#64748b' }, border: { display: false } }
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
                backgroundColor: ['#3B82F6', '#2DD4BF', '#10B981'],
                borderWidth: 0,
                cutout: '70%'
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
@endsection
