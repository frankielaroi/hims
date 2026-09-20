<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HIMS - @yield('title')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        ghana: { red: '#CE1126', gold: '#FCD116', green: '#006B3F', black: '#000000' }
                    }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
    </style>
</head>
<body class="h-full bg-slate-950 text-slate-100 font-sans antialiased">
    <div class="flex h-screen overflow-hidden">
        @auth
            <x-sidebar />
        @endauth

        <div class="flex-1 flex flex-col overflow-y-auto">
            @guest
                <nav class="bg-slate-900 border-b border-slate-800">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between h-16 items-center">
                            <div class="flex items-center space-x-3">
                                <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-gradient-to-br from-emerald-600 to-teal-800 text-white font-bold border border-emerald-500/30">
                                    🇬🇭
                                </div>
                                <span class="text-lg font-bold text-slate-100">HIMS</span>
                            </div>
                        </div>
                    </div>
                </nav>
            @endguest

            <main class="py-8">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    @if(session('success'))
                        <div class="bg-emerald-950 border border-emerald-800 text-emerald-300 px-5 py-4 rounded-lg relative mb-6 flex items-start gap-3">
                            <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                            <p class="text-sm font-medium">{{ session('success') }}</p>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="bg-rose-950 border border-rose-800 text-rose-300 px-5 py-4 rounded-lg relative mb-6 flex items-start gap-3">
                            <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                            <div class="text-sm font-medium">
                                <p class="mb-1">Please fix the following before continuing:</p>
                                <ul class="list-disc list-inside space-y-0.5">
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
</body>
</html>
