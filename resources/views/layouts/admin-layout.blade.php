<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Orion Academy') }} - Admin Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .sidebar-nav-item {
            display: flex;
            align-items: center;
            width: 100%;
            gap: 0.75rem;
            padding: 0.7rem 0.9rem 0.7rem 1rem;
            color: #e5eefb;
            text-decoration: none;
            font-size: 0.85rem;
            line-height: 1.2;
            font-weight: 500;
            white-space: nowrap;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .sidebar-nav-item:hover {
            background-color: rgba(59, 130, 246, 0.2);
        }
        .sidebar-nav-item.active {
            background-color: rgba(59, 130, 246, 0.28);
            border-radius: 0.75rem;
            border: 1px solid rgba(147, 197, 253, 0.45);
            box-shadow: inset 0 0 0 1px rgba(147, 197, 253, 0.2);
        }
        .sidebar-nav-item svg {
            width: 1.05rem;
            height: 1.05rem;
            min-width: 1.05rem;
            min-height: 1.05rem;
            flex-shrink: 0;
            stroke-width: 2;
        }
        .sidebar-nav-item span {
            display: inline-block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-brand-mark {
            width: 3rem;
            height: 3rem;
            min-width: 3rem;
            min-height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-radius: 9999px;
            background: #ffffff;
            padding: 0.2rem;
            box-sizing: border-box;
        }
        .sidebar-brand-mark img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="admin-portal-shell flex h-screen">
        <!-- Sidebar -->
        <aside class="admin-portal-sidebar w-52 bg-blue-900 text-white shadow-lg flex flex-col">
            <!-- Logo Section -->
            <div class="p-3 border-b border-blue-800">
                <div class="flex flex-col items-center text-center">
                    <div class="sidebar-brand-mark mb-2">
                        <img src="/images/orion-logo.png" alt="Orion Christian Academy">
                    </div>
                    <h1 class="text-[8px] font-bold leading-tight tracking-wide">ORION CHRISTIAN ACADEMY</h1>
                </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 overflow-y-auto">
                <ul class="space-y-1">
                    <li>
                        <a href="{{ route('admin.dashboard') }}" class="sidebar-nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-3m0 0l7-4 7 4M5 9v10a1 1 0 001 1h12a1 1 0 001-1V9m-9 11l4-4m0 0l4 4m-4-4V3"></path>
                            </svg>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.students') }}" class="sidebar-nav-item {{ request()->routeIs('admin.students*') ? 'active' : '' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 12H9m6 0a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span>Students</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.parents') }}" class="sidebar-nav-item {{ request()->routeIs('admin.parents*') ? 'active' : '' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0zM4 20h16a2 2 0 002-2v-2a3 3 0 00-5.856-1.487M9 10a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span>Parents / Guardians</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.support-messages') }}" class="sidebar-nav-item {{ request()->routeIs('admin.support-messages*') ? 'active' : '' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m-8 8 3.5-3H18a3 3 0 003-3V6a3 3 0 00-3-3H6a3 3 0 00-3 3v11a3 3 0 002 3z"></path>
                            </svg>
                            <span>Parent Messages</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.entry-records') }}" class="sidebar-nav-item {{ request()->routeIs('admin.entry-records*') ? 'active' : '' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span>Entry Records</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.notifications') }}" class="sidebar-nav-item {{ request()->routeIs('admin.notifications*') ? 'active' : '' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <span>SMS Notifications</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.staff') }}" class="sidebar-nav-item {{ request()->routeIs('admin.staff*') ? 'active' : '' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                            </svg>
                            <span>Staff Accounts</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.profile') }}" class="sidebar-nav-item {{ request()->routeIs('admin.profile*') ? 'active' : '' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span>Profile</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Logout Button -->
            <div class="p-3 border-t border-blue-800">
                <form method="POST" action="{{ route('admin.logout') }}" class="w-full">
                    @csrf
                    <button type="submit" class="sidebar-nav-item w-full justify-start">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="admin-portal-main flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="admin-portal-header bg-white shadow-sm border-b border-gray-200">
                <div class="max-w-full mx-auto px-6 lg:px-8 py-4">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center space-x-4">
                            <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-blue-50 p-1 shadow-sm">
                                <img src="/images/orion-logo.png" alt="Orion Christian Academy" class="h-full w-full object-contain">
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900">Orion Christian Academy</h2>
                            <span class="text-sm text-gray-500 font-medium">ADMINISTRATOR PORTAL</span>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center space-x-2">
                                <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-semibold text-gray-900">Administrator</span>
                                    <span class="text-xs text-gray-500">Admin Account</span>
                                </div>
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                </svg>
                            </div>
                            <form method="POST" action="{{ route('admin.logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="flex items-center space-x-1 text-gray-600 hover:text-gray-900 font-medium">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    <span>Logout</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-auto bg-gray-50">
                @yield('content')
            </main>
        </div>
        @stack('scripts')
    </div>
</body>
</html>
