<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>@yield('title') - Pickup Verification</title>
        @vite(['resources/css/app.css', 'resources/js/parent-dashboard.js'])
    </head>
    <body class="parent-portal-shell">
        @php
            $parentUser = session('supabase_user');
            $parentName = $parentUser['user_metadata']['full_name'] ?? ($parentUser['full_name'] ?? 'Parent');
            $parentRoleLabel = 'Parent / Guardian';
            $parentInitial = strtoupper(substr(trim($parentName), 0, 1) ?: 'P');
        @endphp

        <div class="parent-portal-page">
            <header class="parent-portal-topbar">
                <div class="parent-portal-brand">
                    <img src="/images/orion-logo.png" alt="Orion Christian Academy" class="parent-portal-brand-logo" />
                    <div class="parent-portal-brand-copy">
                        <span>Orion Christian Academy</span>
                        <small>OF THE PHILIPPINES</small>
                    </div>
                </div>

                <div class="parent-portal-user-pill">
                    <div class="parent-portal-user-avatar">{{ $parentInitial }}</div>
                    <div class="parent-portal-user-meta">
                        <span class="parent-portal-user-name">{{ $parentName }}</span>
                        <span class="parent-portal-user-role">{{ $parentRoleLabel }}</span>
                    </div>

                    <form method="POST" action="/logout" class="parent-portal-logout-form">
                        @csrf
                        <button type="submit" class="parent-portal-logout-button" aria-label="Logout">
                            <svg class="parent-portal-logout-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <path d="M16 17l5-5-5-5"></path>
                                <path d="M21 12H9"></path>
                            </svg>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </header>

            <main class="parent-portal-main">
                @yield('content')
            </main>

            <nav class="parent-portal-bottom-nav" aria-label="Parent portal navigation">
                <a href="/parent/dashboard" class="{{ request()->routeIs('parent.dashboard') ? 'active' : '' }}">
                    <span class="parent-portal-nav-icon">⌂</span>
                    <span>Home</span>
                </a>
                <a href="/parent/qr-code" class="{{ request()->routeIs('parent.qr-code') ? 'active' : '' }}">
                    <span class="parent-portal-nav-icon">◫</span>
                    <span>My QR Code</span>
                </a>
                <a href="/parent/pickup-history" class="{{ request()->routeIs('parent.pickup-history') ? 'active' : '' }}">
                    <span class="parent-portal-nav-icon">▣</span>
                    <span>Pickup History</span>
                </a>
                <a href="/parent/profile" class="{{ request()->routeIs('parent.profile') || request()->routeIs('parent.profile.edit') ? 'active' : '' }}">
                    <span class="parent-portal-nav-icon">◉</span>
                    <span>Profile</span>
                </a>
            </nav>
        </div>
    </body>
</html>
