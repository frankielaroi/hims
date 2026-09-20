@extends('layouts.app')

@section('title', 'Healthcare Insights')

@section('content')
<div class="px-6 py-2 space-y-6">
    <!-- Header with Search and User -->
    <div class="flex items-center justify-between h-16 mb-2">
        <div class="flex-1 max-w-lg">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" placeholder="Search patients, staff..." class="block w-full pl-10 pr-3 py-2 bg-white border-none rounded-xl text-sm shadow-sm focus:ring-2 focus:ring-teal-500">
            </div>
        </div>
        <div class="flex items-center space-x-6">
            <button class="text-slate-400 hover:text-slate-600" title="Notifications">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
            </button>
        </div>
    </div>

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', $user->name)[0] }} 👋</h1>
            <p class="text-slate-500 text-sm mt-1">Here's how City Medical Center is doing today.</p>
        </div>
        <div class="bg-white border-none rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm flex items-center">
            Last 7 days
            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-white">
            <p class="text-sm font-medium text-slate-500 mb-1">Active admissions</p>
            <div class="flex items-baseline space-x-2">
                <p class="text-2xl font-bold text-slate-900">784</p>
                <span class="text-xs font-semibold text-green-500">+3.1%</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-white">
            <p class="text-sm font-medium text-slate-500 mb-1">Patients seen today</p>
            <div class="flex items-baseline space-x-2">
                <p class="text-2xl font-bold text-slate-900">1,245</p>
                <span class="text-xs font-semibold text-green-500">+5.7%</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-white">
            <p class="text-sm font-medium text-slate-500 mb-1">Beds occupied</p>
            <div class="flex items-baseline space-x-2">
                <p class="text-2xl font-bold text-slate-900">89%</p>
                <span class="text-xs font-semibold text-red-500">Running high</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-white">
            <p class="text-sm font-medium text-slate-500 mb-1">Average wait time</p>
            <div class="flex items-baseline space-x-2">
                <p class="text-2xl font-bold text-slate-900">12 min</p>
                <span class="text-xs font-semibold text-teal-500">Good</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Admission Trends Chart -->
        <div class="lg:col-span-2 bg-white p-6 rounded-3xl shadow-sm border border-white">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-slate-800">Admissions this week</h3>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-blue-400 rounded-full mr-2"></span>
                        <span class="text-xs font-medium text-slate-500">Admissions</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-green-400 rounded-full mr-2"></span>
                        <span class="text-xs font-medium text-slate-500">Discharges</span>
                    </div>
                </div>
            </div>
            <div class="h-64">
                <canvas id="admissionChart"></canvas>
            </div>
        </div>

        <!-- Department Activity -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-white">
            <h3 class="text-lg font-bold text-slate-800 mb-6">Busiest departments</h3>
            <div class="space-y-4">
                @php
                    $depts = [
                        ['name' => 'Emergency', 'val' => 94, 'color' => 'bg-blue-400'],
                        ['name' => 'Surgery', 'val' => 28, 'color' => 'bg-green-400'],
                        ['name' => 'Pediatrics', 'val' => 65, 'color' => 'bg-green-500'],
                        ['name' => 'OPD', 'val' => 115, 'color' => 'bg-blue-300'],
                        ['name' => 'Other', 'val' => 13, 'color' => 'bg-slate-300'],
                    ];
                @endphp
                @foreach($depts as $dept)
                <div>
                    <div class="flex justify-between text-xs font-medium text-slate-500 mb-1">
                        <span>{{ $dept['name'] }}</span>
                        <span>{{ $dept['val'] }}</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-4 overflow-hidden">
                        <div class="{{ $dept['color'] }} h-full rounded-full" style="width: {{ ($dept['val'] / 120) * 100 }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Staff Availability -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-white">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Who's on today</h3>
            <div class="flex items-center justify-between">
                <div class="w-1/2">
                    <canvas id="staffChart"></canvas>
                </div>
                <div class="w-1/2 pl-6 space-y-4">
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Doctors</p>
                            <p class="text-xs text-slate-400">112 on duty</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-teal-400 rounded-full mr-2"></span>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Nurses</p>
                            <p class="text-xs text-slate-400">218 on duty</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Admin staff</p>
                            <p class="text-xs text-slate-400">45 on duty</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Discharges -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-white">
            <h3 class="text-lg font-bold text-slate-800 mb-6">Ready to go home</h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-100">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center text-red-500 mr-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Awaiting sign-off</p>
                            <p class="text-xs text-slate-400">Discharges</p>
                        </div>
                    </div>
                    <span class="text-xs font-medium text-slate-400">Just now</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-100">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-teal-100 rounded-lg flex items-center justify-center text-teal-500 mr-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Cleared from surgery</p>
                            <p class="text-xs text-slate-400">Discharges</p>
                        </div>
                    </div>
                    <span class="text-xs font-medium text-slate-400">1 hour ago</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-100">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center text-green-500 mr-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">New today</p>
                            <p class="text-xs text-slate-400">Discharges</p>
                        </div>
                    </div>
                    <span class="text-xs font-medium text-slate-400">3 hours ago</span>
                </div>
            </div>
        </div>

        <!-- Critical Notifications -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-white">
            <h3 class="text-lg font-bold text-slate-800 mb-6">Needs your attention</h3>
            <div class="space-y-4">
                <div class="p-4 bg-red-50 rounded-2xl border border-red-100 flex items-start">
                    <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center text-red-600 mr-3 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <p class="text-sm font-semibold text-red-800 leading-snug">A few drugs are running low in stock</p>
                </div>
                <div class="p-4 bg-red-50 rounded-2xl border border-red-100 flex items-start">
                    <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center text-red-600 mr-3 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <p class="text-sm font-semibold text-red-800 leading-snug">Some equipment needs servicing soon</p>
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
                y: { min: 80, max: 140, grid: { color: '#f1f5f9' }, border: { display: false } },
                x: { grid: { display: false }, border: { display: false } }
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
                backgroundColor: ['#3B82F6', '#2DD4BF', '#22C55E'],
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
