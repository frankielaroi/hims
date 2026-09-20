@php
    $user = Auth::user();
    $roles = $user->roles->pluck('slug')->toArray();

    $navItems = [
        [
            'name' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>',
            'active' => request()->routeIs('dashboard'),
            'roles' => ['all']
        ],
        [
            'name' => 'Patients',
            'route' => 'patients.index',
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>',
            'active' => request()->routeIs('patients.*'),
            'roles' => ['all']
        ],
        [
            'name' => 'Staff',
            'route' => 'admin.dashboard',
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>',
            'active' => request()->routeIs('admin.dashboard'),
            'roles' => ['admin']
        ],
        [
            'name' => 'Triage',
            'route' => 'triage.index',
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>',
            'active' => request()->routeIs('triage.*'),
            'roles' => ['nurse', 'admin']
        ],
        [
            'name' => 'Consultation',
            'route' => 'consultations.index',
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>',
            'active' => request()->routeIs('consultations.*'),
            'roles' => ['doctor', 'admin']
        ],
        [
            'name' => 'Laboratory',
            'route' => 'lab.index',
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v3m6-3v3M9 21v-3m6 3v-3M5 9H2m3 6H2m20-6h-3m3 6h-3M9 21h6a2 2 0 002-2V5a2 2 0 00-2-2H9a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>',
            'active' => request()->routeIs('lab.*'),
            'roles' => ['lab_tech', 'admin']
        ],
        [
            'name' => 'Schedule',
            'route' => 'dashboard',
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>',
            'active' => false,
            'roles' => ['all']
        ],
        [
            'name' => 'Reporting',
            'route' => 'dashboard',
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>',
            'active' => false,
            'roles' => ['admin']
        ],
        [
            'name' => 'Analytics',
            'route' => 'dashboard',
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>',
            'active' => false,
            'roles' => ['admin']
        ],
        [
            'name' => 'Pharmacy',
            'route' => 'pharmacy.index',
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.212 1.212 3.18 1.212 4.392 0z"></path></svg>',
            'active' => request()->routeIs('pharmacy.*'),
            'roles' => ['pharmacist', 'admin']
        ],
        [
            'name' => 'Billing',
            'route' => 'billing.index',
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 8h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V15a2 2 0 01-2 2z"></path></svg>',
            'active' => request()->routeIs('billing.*'),
            'roles' => ['cashier', 'admin']
        ],
        [
            'name' => 'Service Pricing',
            'route' => 'admin.services.index',
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-8c-1.11 0-2.08-.402-2.599-1"></path></svg>',
            'active' => request()->routeIs('admin.services.*'),
            'roles' => ['admin']
        ],
        [
            'name' => 'Settings',
            'route' => 'dashboard',
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c-.94 1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>',
            'active' => false,
            'roles' => ['admin']
        ],
    ];
@endphp

<div class="flex flex-col w-64 sidebar-teal h-screen sticky top-0 shadow-xl transition-all duration-300">
    <div class="flex items-center px-6 h-20 mb-4">
        <div class="flex items-center space-x-3">
            <div class="bg-white/20 p-2 rounded-xl text-white">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
        <button class="ml-auto text-white/60 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>
    </div>

    <div class="flex flex-col flex-1 px-4 overflow-y-auto">
        <nav class="flex-1 space-y-1">
            @foreach($navItems as $item)
                @if(in_array('all', $item['roles']) || count(array_intersect($roles, $item['roles'])) > 0)
                    <a href="{{ route($item['route']) }}"
                       class="sidebar-link group flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-200 {{ $item['active'] ? 'active text-white bg-white/10' : 'text-white/60 hover:text-white hover:bg-white/5' }}">
                        <span class="mr-3">
                            {!! $item['icon'] !!}
                        </span>
                        {{ $item['name'] }}
                    </a>
                @endif
            @endforeach
        </nav>

        <div class="pb-6 pt-4 border-t border-white/10 space-y-3">
            <div class="flex items-center px-4">
                <img class="h-9 w-9 rounded-full border-2 border-white/20" src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=ffffff&color=2c5e67" alt="{{ $user->name }}">
                <div class="ml-3 overflow-hidden">
                    <p class="text-sm font-semibold text-white truncate">{{ $user->name }}</p>
                    <p class="text-[11px] text-white/50 truncate">{{ implode(' & ', array_map('ucfirst', $roles)) ?: 'Staff' }}</p>
                </div>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center px-4 py-3 text-sm font-medium text-white/50 hover:text-white hover:bg-white/10 rounded-xl transition-all">
                    <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    Sign out
                </button>
            </form>
        </div>
    </div>
</div>
