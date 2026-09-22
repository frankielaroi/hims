<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HIMS - @yield('title')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94A3B8; }
        :focus-visible { outline: 2px solid #2563EB; outline-offset: 2px; }
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-700 font-sans antialiased">
    <div class="flex h-screen overflow-hidden">
        @auth
            <x-sidebar />
        @endauth

        <div class="flex-1 flex flex-col overflow-y-auto">
            @guest
                <nav class="bg-white border-b border-slate-200">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex h-16 items-center gap-3">
                            <img src="/hims.png" class="h-9 w-auto" alt="HIMS logo">
                            <span class="text-lg font-semibold text-brand-700">HIMS</span>
                        </div>
                    </div>
                </nav>
            @endguest

            <main class="py-8">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    @if(session('success'))
                        <div class="bg-brand-50 border border-brand-200 text-brand-900 px-5 py-4 rounded-lg mb-6 flex items-start gap-3">
                            <i data-lucide="check-circle" class="w-5 h-5 text-brand-600 shrink-0 mt-0.5"></i>
                            <p class="text-sm font-medium">{{ session('success') }}</p>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="bg-red-50 border border-red-200 text-red-800 px-5 py-4 rounded-lg mb-6 flex items-start gap-3">
                            <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 shrink-0 mt-0.5"></i>
                            <p class="text-sm font-medium">{{ session('error') }}</p>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="bg-white border border-red-200 text-red-800 px-5 py-4 rounded-lg mb-6 flex items-start gap-3">
                            <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 shrink-0 mt-0.5"></i>
                            <div class="text-sm">
                                <p class="font-medium mb-1">Please fix the following before continuing:</p>
                                <ul class="list-disc list-inside space-y-0.5 text-red-700">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <script>
        if (window.lucide) { lucide.createIcons(); }
    </script>
    @stack('scripts')
</body>
</html>
