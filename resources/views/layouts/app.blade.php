<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $rawTitle = trim($__env->yieldContent('title', 'Exam Verification System'));
        $documentTitle = \Illuminate\Support\Str::startsWith($rawTitle, 'CERNIX')
            ? $rawTitle
            : 'CERNIX — ' . $rawTitle;
    @endphp
    <title>{{ $documentTitle }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @stack('head')
</head>
<body class="bg-gray-50 min-h-screen">

    <nav class="bg-[#0f2050] text-white shadow-lg">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between h-14">
                <a href="/" class="text-lg font-bold tracking-widest uppercase">CERNIX</a>
                <div class="hidden sm:flex items-center gap-1 text-sm">
                    <a href="/student/register"
                       class="px-3 py-2 rounded hover:bg-white/10 transition {{ request()->is('student*') ? 'bg-white/20' : '' }}">
                        Student Portal
                    </a>
                    <a href="/examiner/dashboard"
                       class="px-3 py-2 rounded hover:bg-white/10 transition {{ request()->is('examiner*') ? 'bg-white/20' : '' }}">
                        Examiner Portal
                    </a>
                    <a href="/admin/dashboard"
                       class="px-3 py-2 rounded hover:bg-white/10 transition {{ request()->is('admin*') ? 'bg-white/20' : '' }}">
                        Admin Portal
                    </a>
                </div>
                <!-- Mobile menu button -->
                <button id="nav-toggle" class="sm:hidden p-2 rounded hover:bg-white/10">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
            <!-- Mobile nav -->
            <div id="nav-mobile" class="hidden sm:hidden pb-3 text-sm">
                <a href="/student/register" class="block px-3 py-2 rounded hover:bg-white/10">Student Portal</a>
                <a href="/examiner/dashboard" class="block px-3 py-2 rounded hover:bg-white/10">Examiner Portal</a>
                <a href="/admin/dashboard" class="block px-3 py-2 rounded hover:bg-white/10">Admin Portal</a>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
        @yield('content')
    </main>

    <script>
        document.getElementById('nav-toggle')?.addEventListener('click', () => {
            document.getElementById('nav-mobile').classList.toggle('hidden');
        });
    </script>
    @stack('scripts')
</body>
</html>
