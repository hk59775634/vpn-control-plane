<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '管理后台') - VPN SaaS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x/dist/cdn.min.js"></script>
    @stack('styles')
</head>
<body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-800 antialiased" style="font-family: 'Plus Jakarta Sans', ui-sans-serif, sans-serif;">
    <div class="flex min-h-0 flex-1 flex-col" x-data="adminDashboard()" x-init="init()">
        <x-admin.topbar brand="VPN SaaS · 控制面" :show-search="false">
            <x-admin.topbar-user-menu>
                <button type="button" @click="userMenuOpen = false; setTab('security')" class="block w-full px-3 py-2 text-left text-sm text-slate-300 hover:bg-slate-700 hover:text-white">
                    修改密码
                </button>
                <button type="button" @click="userMenuOpen = false; logout()" class="block w-full border-t border-slate-700 px-3 py-2 text-left text-sm text-slate-300 hover:bg-slate-700 hover:text-white">
                    退出登录
                </button>
            </x-admin.topbar-user-menu>
        </x-admin.topbar>
        <div class="flex min-h-0 flex-1 pt-14">
            <x-admin.sidebar brand="管理后台" subtitle="控制台" :brand-url="url('/admin')">
                @yield('sidebar')
                <x-slot:footer>
                    @yield('sidebar_footer')
                </x-slot:footer>
            </x-admin.sidebar>
            {{-- 主内容区 --}}
            <div class="console-main">
                <header class="console-header">
                    <h1 class="text-lg font-semibold text-slate-900" x-text="(tabTitles && tabTitles[tab]) ? tabTitles[tab] : '控制台'">控制台</h1>
                    <div class="flex items-center gap-3">
                        @yield('header_actions')
                    </div>
                </header>
                <div class="console-content">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
    {{-- 全局 Toast 与确认框（依赖 Alpine.store） --}}
    <x-admin.toast />
    <x-admin.modal-confirm />
    @stack('scripts')
</body>
</html>
