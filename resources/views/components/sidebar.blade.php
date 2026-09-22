@php
    $user = Auth::user();
    $roles = $user->roles->pluck('slug')->toArray();

    $navItems = [
        [
            'name' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'layout-dashboard',
            'active' => request()->routeIs('dashboard'),
            'roles' => ['all']
        ],
        [
            'name' => 'Patients',
            'route' => 'patients.index',
            'icon' => 'users',
            'active' => request()->routeIs('patients.*'),
            'roles' => ['all']
        ],
        [
            'name' => 'Emergency',
            'route' => 'emergency.board',
            'icon' => 'alert-triangle',
            'active' => request()->routeIs('emergency.*'),
            'roles' => ['all']
        ],
        [
            'name' => 'Staff',
            'route' => 'admin.dashboard',
            'icon' => 'user-cog',
            'active' => request()->routeIs('admin.dashboard'),
            'roles' => ['admin']
        ],
        [
            'name' => 'Triage',
            'route' => 'triage.index',
            'icon' => 'activity',
            'active' => request()->routeIs('triage.*'),
            'roles' => ['nurse', 'admin']
        ],
        [
            'name' => 'Consultation',
            'route' => 'consultations.index',
            'icon' => 'stethoscope',
            'active' => request()->routeIs('consultations.*'),
            'roles' => ['doctor', 'admin']
        ],
        [
            'name' => 'Tasks',
            'route' => 'tasks.index',
            'icon' => 'list-checks',
            'active' => request()->routeIs('tasks.*'),
            'roles' => ['doctor', 'nurse', 'admin']
        ],
        [
            'name' => 'Radiology',
            'route' => 'radiology.index',
            'icon' => 'scan',
            'active' => request()->routeIs('radiology.*'),
            'roles' => ['radiographer', 'admin']
        ],
        [
            'name' => 'Laboratory',
            'route' => 'lab.index',
            'icon' => 'flask-conical',
            'active' => request()->routeIs('lab.*'),
            'roles' => ['lab_tech', 'admin']
        ],
        [
            'name' => 'Lab Inventory',
            'route' => 'lab.inventory.index',
            'icon' => 'package',
            'active' => request()->routeIs('lab.inventory.*'),
            'roles' => ['lab_tech', 'admin']
        ],
        [
            'name' => 'Wards & Beds',
            'route' => 'admissions.index',
            'icon' => 'bed',
            'active' => request()->routeIs('admissions.*'),
            'roles' => ['nurse', 'admin']
        ],
        [
            'name' => 'Referrals',
            'route' => 'referrals.index',
            'icon' => 'arrow-left-right',
            'active' => request()->routeIs('referrals.*'),
            'roles' => ['doctor', 'nurse', 'admin']
        ],
        [
            'name' => 'Schedule',
            'route' => 'dashboard',
            'icon' => 'calendar',
            'active' => false,
            'roles' => ['all']
        ],
        [
            'name' => 'Reporting',
            'route' => 'dashboard',
            'icon' => 'bar-chart-3',
            'active' => false,
            'roles' => ['admin']
        ],
        [
            'name' => 'Analytics',
            'route' => 'dashboard',
            'icon' => 'trending-up',
            'active' => false,
            'roles' => ['admin']
        ],
        [
            'name' => 'Pharmacy',
            'route' => 'pharmacy.index',
            'icon' => 'pill',
            'active' => request()->routeIs('pharmacy.*'),
            'roles' => ['pharmacist', 'admin']
        ],
        [
            'name' => 'Billing',
            'route' => 'billing.index',
            'icon' => 'credit-card',
            'active' => request()->routeIs('billing.*'),
            'roles' => ['cashier', 'admin']
        ],
        [
            'name' => 'NHIS Claims',
            'route' => 'nhis.index',
            'icon' => 'shield-check',
            'active' => request()->routeIs('nhis.*'),
            'roles' => ['cashier', 'admin']
        ],
        [
            'name' => 'Service Pricing',
            'route' => 'admin.services.index',
            'icon' => 'tag',
            'active' => request()->routeIs('admin.services.*'),
            'roles' => ['admin']
        ],
        [
            'name' => 'Settings',
            'route' => 'dashboard',
            'icon' => 'settings',
            'active' => false,
            'roles' => ['admin']
        ],
    ];
@endphp

<aside class="flex flex-col w-64 bg-white border-r border-slate-200 h-screen sticky top-0 shrink-0 font-lato">
    <div class="flex items-center gap-3 px-5 h-16 border-b border-slate-200">
        <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-brand-50 shrink-0">
            <i data-lucide="heart-pulse" class="w-5 h-5 text-brand-600"></i>
        </div>
        <div class="overflow-hidden">
            <p class="text-sm font-semibold text-slate-900 truncate">HIMS</p>
            <p class="text-[11px] text-slate-500 truncate">MoH / GHS / NHIA</p>
        </div>
    </div>

    <div class="flex flex-col flex-1 px-3 py-4 overflow-y-auto">
        <nav class="flex-1 space-y-0.5" aria-label="Main navigation">
            @foreach($navItems as $item)
                @if(in_array('all', $item['roles']) || count(array_intersect($roles, $item['roles'])) > 0)
                    <a href="{{ route($item['route']) }}"
                       @if($item['active']) aria-current="page" @endif
                       class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors duration-150 {{ $item['active'] ? 'bg-brand-50 text-brand-700 font-semibold' : 'font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                        <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4 mr-3 shrink-0 {{ $item['active'] ? 'text-brand-600' : 'text-slate-400' }}"></i>
                        {{ $item['name'] }}
                    </a>
                @endif
            @endforeach
        </nav>

        <div class="pt-4 mt-4 border-t border-slate-200 space-y-2">
            <div class="flex items-center px-3">
                <img class="h-9 w-9 rounded-full" src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=DBEAFE&color=1D4ED8&bold=true" alt="{{ $user->name }}">
                <div class="ml-3 overflow-hidden">
                    <p class="text-sm font-semibold text-slate-900 truncate">{{ $user->name }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ implode(' & ', array_map('ucfirst', $roles)) ?: 'Staff' }}</p>
                </div>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center px-3 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors">
                    <i data-lucide="log-out" class="w-4 h-4 mr-3 text-slate-400"></i>
                    Sign out
                </button>
            </form>
        </div>
    </div>
</aside>
